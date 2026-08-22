# =============================================================================
# blueprints/auth.py
# -----------------------------------------------------------------------------
# Authentication blueprint for the Dayflow HRMS API.
#
# Endpoints:
#   POST /api/auth/register  — create a new user + employee profile
#   POST /api/auth/login     — verify credentials, return JWT token
#   POST /api/auth/logout    — stateless logout (client discards token)
#
# Auth flow:
#   1. Client POSTs email + password to /login
#   2. Server verifies bcrypt hash
#   3. Server returns a signed JWT containing user_id (sub) + role claim
#   4. Client stores the JWT in session
#   5. All subsequent requests send: Authorization: Bearer <token>
#   6. Flask-JWT-Extended verifies the token on every protected route
# =============================================================================

import logging
from flask import Blueprint, request, jsonify
from flask_jwt_extended import create_access_token, jwt_required, get_jwt_identity

from extensions import db, bcrypt
from models.user import User
from models.employee import Employee

# Re-use the single shared logger from app.py
logger = logging.getLogger("dayflow")

# =============================================================================
# BLUEPRINT DEFINITION
# =============================================================================
# url_prefix="/api/auth" is set when registering in app.py
# So all routes here are relative: "/register" → "/api/auth/register"

auth_bp = Blueprint("auth", __name__)


# =============================================================================
# HELPER: build_user_response
# =============================================================================

def build_user_response(user, token):
    """
    Builds the standard login/register success response dict.
    Keeps the response shape consistent across register and login.

    Args:
        user  (User): the authenticated User model instance
        token (str):  the signed JWT access token string

    Returns:
        dict: JSON-serialisable response payload
    """
    return {
        "token":   token,
        "user_id": user.id,
        "name":    user.name,
        "email":   user.email,
        "role":    user.role,
    }


# =============================================================================
# ROUTE: POST /api/auth/register
# =============================================================================

@auth_bp.route("/register", methods=["POST"])
def register():
    """
    Register a new user.

    Request body (JSON):
        name     (str, required)  — full name
        email    (str, required)  — unique email address
        password (str, required)  — plain text password (min 6 chars)
        role     (str, optional)  — 'employee' (default) or 'admin'

    Success response (201):
        { token, user_id, name, email, role }

    Error responses:
        400 — missing required fields
        409 — email already registered
        500 — database error
    """
    logger.debug("POST /api/auth/register called")

    # ── Step 1: Parse request body ────────────────────────────────────────────
    data = request.get_json()
    if not data:
        logger.warning("Register: no JSON body received")
        return jsonify({"error": "Request body is required"}), 400

    name     = data.get("name",     "").strip()
    email    = data.get("email",    "").strip().lower()
    password = data.get("password", "").strip()
    role     = data.get("role",     "employee").strip().lower()

    # ── Step 2: Validate required fields ─────────────────────────────────────
    missing = [f for f, v in {"name": name, "email": email, "password": password}.items() if not v]
    if missing:
        logger.warning("Register: missing fields %s", missing)
        return jsonify({"error": f"Missing required fields: {', '.join(missing)}"}), 400

    # ── Step 3: Validate role ─────────────────────────────────────────────────
    # Only 'employee' and 'admin' are valid roles
    if role not in ("employee", "admin"):
        logger.warning("Register: invalid role '%s'", role)
        return jsonify({"error": "role must be 'employee' or 'admin'"}), 400

    # ── Step 4: Validate password length ─────────────────────────────────────
    if len(password) < 6:
        logger.warning("Register: password too short for email %s", email)
        return jsonify({"error": "Password must be at least 6 characters"}), 400

    # ── Step 5: Check for duplicate email ────────────────────────────────────
    try:
        existing = User.query.filter_by(email=email).first()
    except Exception as exc:
        logger.error("Register: DB error checking email %s — %s", email, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if existing:
        logger.warning("Register: email '%s' is already registered", email)
        return jsonify({"error": "Email already registered"}), 409

    # ── Step 6: Hash the password ─────────────────────────────────────────────
    # bcrypt.generate_password_hash returns bytes; decode to str for storage
    try:
        hashed_pw = bcrypt.generate_password_hash(password).decode("utf-8")
    except Exception as exc:
        logger.error("Register: bcrypt hashing failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ── Step 7: Create User record ────────────────────────────────────────────
    new_user = User(
        name=name,
        email=email,
        role=role,
        password_hash=hashed_pw,
        is_active=True,
    )
    db.session.add(new_user)

    # flush() assigns new_user.id without committing — needed to create Employee FK
    try:
        db.session.flush()
    except Exception as exc:
        db.session.rollback()
        logger.error("Register: flush failed for email %s — %s", email, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("Register: User created with id=%s email=%s", new_user.id, email)

    # ── Step 8: Create linked Employee profile ────────────────────────────────
    # Every user (including admins) gets an Employee record for profile storage
    new_employee = Employee(
        user_id=new_user.id,
        # All profile fields start empty; user fills them in later
    )
    db.session.add(new_employee)

    # ── Step 9: Commit both records atomically ────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error("Register: commit failed for email %s — %s", email, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("Register: successful — user_id=%s role=%s", new_user.id, role)

    # ── Step 10: Issue JWT token ──────────────────────────────────────────────
    # identity = user_id (stored as 'sub' claim in the token)
    # additional_claims = { "role": "employee" } (used by require_role decorator)
    token = create_access_token(
        identity=str(new_user.id),
        additional_claims={"role": new_user.role},
    )

    return jsonify(build_user_response(new_user, token)), 201


# =============================================================================
# ROUTE: POST /api/auth/login
# =============================================================================

@auth_bp.route("/login", methods=["POST"])
def login():
    """
    Authenticate an existing user.

    Request body (JSON):
        email    (str, required) — registered email address
        password (str, required) — plain text password

    Success response (200):
        { token, user_id, name, email, role }

    Error responses:
        400 — missing fields
        401 — invalid email or password
        403 — account is disabled
        500 — database error
    """
    logger.debug("POST /api/auth/login called")

    # ── Step 1: Parse request body ────────────────────────────────────────────
    data = request.get_json()
    if not data:
        logger.warning("Login: no JSON body received")
        return jsonify({"error": "Request body is required"}), 400

    email    = data.get("email",    "").strip().lower()
    password = data.get("password", "").strip()

    # ── Step 2: Validate required fields ─────────────────────────────────────
    if not email or not password:
        logger.warning("Login: missing email or password")
        return jsonify({"error": "email and password are required"}), 400

    logger.debug("Login: attempt for email=%s", email)

    # ── Step 3: Look up the user by email ─────────────────────────────────────
    try:
        user = User.query.filter_by(email=email).first()
    except Exception as exc:
        logger.error("Login: DB error looking up email %s — %s", email, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ── Step 4: Check user exists ─────────────────────────────────────────────
    # Deliberately vague error message — don't reveal whether email exists
    if not user:
        logger.warning("Login: no account found for email=%s", email)
        return jsonify({"error": "Invalid email or password"}), 401

    # ── Step 5: Check account is active ──────────────────────────────────────
    if not user.is_active:
        logger.warning("Login: disabled account login attempt for email=%s", email)
        return jsonify({"error": "Account is disabled — contact HR"}), 403

    # ── Step 6: Verify password ───────────────────────────────────────────────
    try:
        password_valid = bcrypt.check_password_hash(user.password_hash, password)
    except Exception as exc:
        logger.error("Login: bcrypt check failed for email %s — %s", email, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not password_valid:
        logger.warning("Login: wrong password for email=%s", email)
        return jsonify({"error": "Invalid email or password"}), 401

    logger.debug("Login: credentials verified for user_id=%s role=%s", user.id, user.role)

    # ── Step 7: Issue JWT token ───────────────────────────────────────────────
    token = create_access_token(
        identity=str(user.id),
        additional_claims={"role": user.role},
    )

    logger.debug("Login: JWT issued for user_id=%s", user.id)

    return jsonify(build_user_response(user, token)), 200


# =============================================================================
# ROUTE: POST /api/auth/logout
# =============================================================================

@auth_bp.route("/logout", methods=["POST"])
@jwt_required()
def logout():
    """
    Stateless logout.

    This API uses short-lived JWTs — no server-side token blacklist.
    The client is responsible for discarding the token from its session.
    This endpoint exists so the Laravel frontend can make an explicit
    logout call and receive a clean 200 response.

    Success response (200):
        { message: "Logged out successfully" }
    """
    user_id = get_jwt_identity()
    logger.debug("POST /api/auth/logout — user_id=%s", user_id)
    return jsonify({"message": "Logged out successfully"}), 200
