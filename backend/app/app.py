# ─── app.py ──────────────────────────────────────────────────────────────────

import os
import logging
from flask import Flask, jsonify
from flask_cors import CORS
from dotenv import load_dotenv
from extensions import db, bcrypt, migrate, jwt

logging.basicConfig(
    level=logging.DEBUG,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("dayflow")

load_dotenv()

logger.debug("Creating Flask app")
app = Flask(__name__)
CORS(app)

# ─── Config ───────────────────────────────────────────────────────────────────
db_url = os.environ.get("DATABASE_URL")
if not db_url:
    logger.warning("DATABASE_URL is not set — SQLALCHEMY_DATABASE_URI will be None")
else:
    logger.debug("DATABASE_URL found")

app.config["SQLALCHEMY_DATABASE_URI"] = db_url
app.config["SQLALCHEMY_TRACK_MODIFICATIONS"] = False
app.config["JWT_SECRET_KEY"] = os.environ.get("JWT_SECRET_KEY", "dayflow-secret-change-me")

# ─── Init Extensions ──────────────────────────────────────────────────────────
db.init_app(app)
migrate.init_app(app, db)
bcrypt.init_app(app)
jwt.init_app(app)
logger.debug("Extensions initialised")

# ─── Global Error Handlers ────────────────────────────────────────────────────

@app.errorhandler(404)
def not_found(e):
    return jsonify({"error": "Not found"}), 404


@app.errorhandler(405)
def method_not_allowed(e):
    return jsonify({"error": "Method not allowed"}), 405


@app.errorhandler(Exception)
def unhandled_exception(e):
    logger.error("Unhandled exception: %s", e, exc_info=True)
    return jsonify({"error": "Internal server error"}), 500


# ─── Health ───────────────────────────────────────────────────────────────────

@app.route("/health", methods=["GET"])
def health():
    logger.debug("GET /health called")
    return jsonify({"status": "ok"}), 200


# ─── Register Blueprints (uncommented as each sub-task is completed) ──────────
# from blueprints.auth import auth_bp
# from blueprints.employees import employees_bp
# from blueprints.attendance import attendance_bp
# from blueprints.leave import leave_bp
# from blueprints.payroll import payroll_bp
# from blueprints.analytics import analytics_bp
# app.register_blueprint(auth_bp,       url_prefix="/api/auth")
# app.register_blueprint(employees_bp,  url_prefix="/api/employees")
# app.register_blueprint(attendance_bp, url_prefix="/api/attendance")
# app.register_blueprint(leave_bp,      url_prefix="/api/leave")
# app.register_blueprint(payroll_bp,    url_prefix="/api/payroll")
# app.register_blueprint(analytics_bp,  url_prefix="/api/analytics")


if __name__ == "__main__":
    logger.debug("Starting Flask development server on 0.0.0.0:5000")
    app.run(host="0.0.0.0", port=5000)
