# =============================================================================
# blueprints/employees.py
# -----------------------------------------------------------------------------
# Employee profile blueprint for the Dayflow HRMS API.
#
# Endpoints:
#   GET  /api/employees/me        — employee views their own profile
#   PUT  /api/employees/me        — employee edits limited fields (phone, address, picture)
#   GET  /api/employees           — admin lists all employees
#   GET  /api/employees/<id>      — admin views one employee's full profile
#   PUT  /api/employees/<id>      — admin edits all fields of any employee
#
# Access rules (enforced by JWT + require_role):
#   /me  routes   → any authenticated user (employee or admin)
#   /<id> routes  → admin only
# =============================================================================

import logging
from flask import Blueprint, request, jsonify
from flask_jwt_extended import jwt_required, get_jwt_identity

from extensions import db
from models.employee import Employee
from models.user import User
from utils.auth_helpers import require_role

logger = logging.getLogger("dayflow")

# =============================================================================
# BLUEPRINT DEFINITION
# =============================================================================
# url_prefix="/api/employees" is set when registering in app.py

employees_bp = Blueprint("employees", __name__)


# =============================================================================
# HELPERS
# =============================================================================

def get_employee_by_user_id(user_id):
    """
    Fetches the Employee record linked to the given user_id.

    Args:
        user_id (int): the user's primary key from the JWT identity

    Returns:
        Employee | None
    """
    return Employee.query.filter_by(user_id=user_id).first()


# Fields an employee is allowed to update on their own profile.
# All other fields (job_title, department) require admin access.
EMPLOYEE_EDITABLE_FIELDS = {"phone", "address", "profile_picture"}

# All fields an admin can update on any employee profile.
ADMIN_EDITABLE_FIELDS = {"phone", "address", "profile_picture", "job_title", "department"}


# =============================================================================
# ROUTE: GET /api/employees/me
# =============================================================================

@employees_bp.route("/me", methods=["GET"])
@jwt_required()
@require_role("employee", "admin")
def get_my_profile():
    """
    Returns the authenticated user's own employee profile.

    The user_id is extracted from the JWT token — no user input needed.
    Joins Employee with User to include name, email, role in the response.

    Success response (200):
        { id, user_id, name, email, role, phone, address,
          job_title, department, profile_picture, created_at }

    Error responses:
        404 — employee profile not found (shouldn't happen after registration)
        500 — database error
    """
    user_id = int(get_jwt_identity())
    logger.debug("GET /api/employees/me — user_id=%s", user_id)

    # ── Fetch employee record ─────────────────────────────────────────────────
    try:
        employee = get_employee_by_user_id(user_id)
    except Exception as exc:
        logger.error("GET /me: DB error for user_id=%s — %s", user_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee:
        logger.warning("GET /me: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    logger.debug("GET /me: returning profile for user_id=%s", user_id)
    return jsonify(employee.to_dict()), 200


# =============================================================================
# ROUTE: PUT /api/employees/me
# =============================================================================

@employees_bp.route("/me", methods=["PUT"])
@jwt_required()
@require_role("employee", "admin")
def update_my_profile():
    """
    Allows an employee to update their own limited profile fields.

    Only these fields are writable by the employee themselves:
        phone, address, profile_picture

    Fields like job_title and department can only be changed by admin.
    Any attempt to send those fields is silently ignored (not an error).

    Request body (JSON, all optional):
        phone           (str)
        address         (str)
        profile_picture (str)

    Success response (200):
        { updated employee profile }

    Error responses:
        404 — employee profile not found
        500 — database error
    """
    user_id = int(get_jwt_identity())
    logger.debug("PUT /api/employees/me — user_id=%s", user_id)

    # ── Fetch employee record ─────────────────────────────────────────────────
    try:
        employee = get_employee_by_user_id(user_id)
    except Exception as exc:
        logger.error("PUT /me: DB error for user_id=%s — %s", user_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee:
        logger.warning("PUT /me: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    # ── Parse and apply only whitelisted fields ───────────────────────────────
    data = request.get_json() or {}
    updated_fields = []

    for field in EMPLOYEE_EDITABLE_FIELDS:
        if field in data:
            setattr(employee, field, data[field])
            updated_fields.append(field)

    # Log a warning if the client tried to set restricted fields
    restricted_attempts = [f for f in data if f in (ADMIN_EDITABLE_FIELDS - EMPLOYEE_EDITABLE_FIELDS)]
    if restricted_attempts:
        logger.warning(
            "PUT /me: user_id=%s attempted to set restricted fields %s — ignored",
            user_id, restricted_attempts,
        )

    logger.debug("PUT /me: updating fields %s for user_id=%s", updated_fields, user_id)

    # ── Commit changes ────────────────────────────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error("PUT /me: commit failed for user_id=%s — %s", user_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("PUT /me: update successful for user_id=%s", user_id)
    return jsonify(employee.to_dict()), 200


# =============================================================================
# ROUTE: GET /api/employees  (admin only)
# =============================================================================

@employees_bp.route("", methods=["GET"])
@jwt_required()
@require_role("admin")
def list_employees():
    """
    Returns a list of all employees with their profile and user info.
    Admin only.

    Optional query parameters:
        department (str) — filter by department name (case-insensitive)

    Success response (200):
        { employees: [ { ...employee dict }, ... ], total: N }

    Error responses:
        500 — database error
    """
    logger.debug("GET /api/employees — admin list all employees")

    # ── Optional department filter ────────────────────────────────────────────
    department_filter = request.args.get("department", "").strip()

    try:
        # Join Employee with User so we can filter/sort by user fields if needed
        query = Employee.query.join(User, Employee.user_id == User.id)

        if department_filter:
            logger.debug("GET /employees: filtering by department='%s'", department_filter)
            query = query.filter(Employee.department.ilike(f"%{department_filter}%"))

        employees = query.all()

    except Exception as exc:
        logger.error("GET /employees: DB error — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("GET /employees: returning %d employees", len(employees))
    return jsonify({
        "employees": [e.to_dict() for e in employees],
        "total":     len(employees),
    }), 200


# =============================================================================
# ROUTE: GET /api/employees/<id>  (admin only)
# =============================================================================

@employees_bp.route("/<int:employee_id>", methods=["GET"])
@jwt_required()
@require_role("admin")
def get_employee(employee_id):
    """
    Returns a single employee's full profile by employee ID.
    Admin only.

    Path parameter:
        employee_id (int) — the Employee table primary key

    Success response (200):
        { ...employee profile dict }

    Error responses:
        404 — employee not found
        500 — database error
    """
    logger.debug("GET /api/employees/%s — admin fetch one employee", employee_id)

    # ── Fetch by primary key ──────────────────────────────────────────────────
    try:
        employee = Employee.query.get(employee_id)
    except Exception as exc:
        logger.error("GET /employees/%s: DB error — %s", employee_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee:
        logger.warning("GET /employees/%s: not found", employee_id)
        return jsonify({"error": "Employee not found"}), 404

    logger.debug("GET /employees/%s: returning profile", employee_id)
    return jsonify(employee.to_dict()), 200


# =============================================================================
# ROUTE: PUT /api/employees/<id>  (admin only)
# =============================================================================

@employees_bp.route("/<int:employee_id>", methods=["PUT"])
@jwt_required()
@require_role("admin")
def update_employee(employee_id):
    """
    Allows admin to update any field on any employee's profile,
    including job_title, department, and the user's name.

    Admin can update:
        phone, address, profile_picture  — contact fields
        job_title, department            — job fields (only admin can change these)
        name                             — updates the linked User.name

    Path parameter:
        employee_id (int) — the Employee table primary key

    Request body (JSON, all optional):
        phone, address, profile_picture, job_title, department, name

    Success response (200):
        { updated employee profile }

    Error responses:
        404 — employee not found
        500 — database error
    """
    admin_user_id = int(get_jwt_identity())
    logger.debug("PUT /api/employees/%s — by admin user_id=%s", employee_id, admin_user_id)

    # ── Fetch employee ────────────────────────────────────────────────────────
    try:
        employee = Employee.query.get(employee_id)
    except Exception as exc:
        logger.error("PUT /employees/%s: DB error — %s", employee_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee:
        logger.warning("PUT /employees/%s: not found", employee_id)
        return jsonify({"error": "Employee not found"}), 404

    # ── Apply all admin-editable fields ───────────────────────────────────────
    data = request.get_json() or {}
    updated_fields = []

    for field in ADMIN_EDITABLE_FIELDS:
        if field in data:
            setattr(employee, field, data[field])
            updated_fields.append(field)

    # Admin can also update the user's display name
    if "name" in data and employee.user:
        employee.user.name = data["name"]
        updated_fields.append("name")

    logger.debug(
        "PUT /employees/%s: updating fields %s by admin user_id=%s",
        employee_id, updated_fields, admin_user_id,
    )

    # ── Commit changes ────────────────────────────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error(
            "PUT /employees/%s: commit failed — %s", employee_id, exc, exc_info=True
        )
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("PUT /employees/%s: update successful", employee_id)
    return jsonify(employee.to_dict()), 200
