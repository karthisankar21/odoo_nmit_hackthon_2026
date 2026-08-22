# =============================================================================
# app.py
# -----------------------------------------------------------------------------
# Flask application factory for the Dayflow HRMS backend.
#
# Responsibilities:
#   1. Configure logging (DEBUG in dev, WARNING in prod)
#   2. Load environment variables from .env
#   3. Configure Flask + SQLAlchemy + JWT
#   4. Initialise all extensions (db, migrate, bcrypt, jwt)
#   5. Register global JSON error handlers (404, 405, 500)
#   6. Register all API blueprints under /api/*
#   7. Expose /health for Docker healthcheck
# =============================================================================

import os
import logging
from flask import Flask, jsonify
from flask_cors import CORS
from dotenv import load_dotenv
from extensions import db, bcrypt, migrate, jwt

# ── Import all models so Flask-Migrate can detect every table ─────────────────
# This must happen after db is imported but before migrate.init_app()
from models import User, Employee, Attendance, LeaveRequest, Payroll  # noqa: F401

logging.basicConfig(
    level=logging.DEBUG,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("dayflow")

load_dotenv()

logger.debug("Creating Flask app")

# =============================================================================
# APPLICATION FACTORY
# =============================================================================
app = Flask(__name__)

# Allow requests from the Laravel frontend container
CORS(app)

# =============================================================================
# CONFIGURATION
# =============================================================================

# ── Database ──────────────────────────────────────────────────────────────────
# DATABASE_URL is injected by docker-compose as an environment variable.
# Format: postgresql://user:password@host:port/dbname
db_url = os.environ.get("DATABASE_URL")
if not db_url:
    logger.warning("DATABASE_URL is not set — database operations will fail")
else:
    logger.debug("DATABASE_URL found — connecting to PostgreSQL")

app.config["SQLALCHEMY_DATABASE_URI"]        = db_url
app.config["SQLALCHEMY_TRACK_MODIFICATIONS"] = False   # suppress deprecation warning

# ── JWT ───────────────────────────────────────────────────────────────────────
# JWT_SECRET_KEY is injected by docker-compose.
# In production this must be a strong random secret.
app.config["JWT_SECRET_KEY"] = os.environ.get("JWT_SECRET_KEY", "dayflow-secret-change-me")

# =============================================================================
# INITIALISE EXTENSIONS
# =============================================================================
# Each extension is initialised with the app instance here.
# Extensions themselves are defined in extensions.py (avoids circular imports).

db.init_app(app)       # SQLAlchemy — ORM + connection pool
migrate.init_app(app, db)  # Flask-Migrate — Alembic migration support
bcrypt.init_app(app)   # Flask-Bcrypt — password hashing
jwt.init_app(app)      # Flask-JWT-Extended — JWT token handling
logger.debug("All extensions initialised")

# =============================================================================
# GLOBAL ERROR HANDLERS
# =============================================================================
# These ensure ALL error responses are JSON — never raw HTML.
# This matters because the Laravel frontend parses every response as JSON.

@app.errorhandler(404)
def not_found(e):
    """
    Handles 404 Not Found.
    Triggered when a route does not exist.
    """
    return jsonify({"error": "Not found"}), 404


@app.errorhandler(405)
def method_not_allowed(e):
    """
    Handles 405 Method Not Allowed.
    Triggered when the HTTP method is wrong for a route (e.g. GET on a POST route).
    """
    return jsonify({"error": "Method not allowed"}), 405


@app.errorhandler(Exception)
def unhandled_exception(e):
    """
    Catch-all handler for any unhandled Python exception.
    Logs the full traceback at ERROR level.
    Returns a generic 500 JSON response — never leaks stack traces to the client.
    """
    logger.error("Unhandled exception: %s", e, exc_info=True)
    return jsonify({"error": "Internal server error"}), 500


# =============================================================================
# ROUTES
# =============================================================================

@app.route("/", methods=["GET"])
def root():
    """
    Root endpoint — confirms the API is reachable.
    Useful for a quick browser check.
    """
    return jsonify({"app": "Dayflow HRMS API", "status": "running"}), 200


@app.route("/health", methods=["GET"])
def health():
    """
    Health-check endpoint used by Docker Compose healthcheck.
    Returns 200 OK when the Flask process is alive.
    """
    logger.debug("GET /health called")
    return jsonify({"status": "ok"}), 200


# =============================================================================
# BLUEPRINT REGISTRATION
# =============================================================================
# Blueprints are uncommented one by one as each sub-task is completed.
# Each blueprint owns one feature module (auth, employees, attendance, etc.)

from blueprints.auth       import auth_bp         # Sub-Task 3 ✅
from blueprints.employees  import employees_bp    # Sub-Task 4 ✅
from blueprints.attendance import attendance_bp   # Sub-Task 5 ✅
from blueprints.leave      import leave_bp        # Sub-Task 6 ✅
from blueprints.payroll    import payroll_bp      # Sub-Task 7 ✅
from blueprints.analytics  import analytics_bp   # Sub-Task 8 ✅

app.register_blueprint(auth_bp,       url_prefix="/api/auth")
app.register_blueprint(employees_bp,  url_prefix="/api/employees")
app.register_blueprint(attendance_bp, url_prefix="/api/attendance")
app.register_blueprint(leave_bp,      url_prefix="/api/leave")
app.register_blueprint(payroll_bp,    url_prefix="/api/payroll")
app.register_blueprint(analytics_bp,  url_prefix="/api/analytics")


# =============================================================================
# ENTRY POINT
# =============================================================================

if __name__ == "__main__":
    logger.debug("Starting Flask development server on 0.0.0.0:5000")
    app.run(host="0.0.0.0", port=5000)
