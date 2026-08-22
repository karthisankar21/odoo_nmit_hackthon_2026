# =============================================================================
# blueprints/attendance.py
# -----------------------------------------------------------------------------
# Attendance blueprint for the Dayflow HRMS API.
#
# Endpoints:
#   POST /api/attendance/check-in          — employee checks in for today
#   POST /api/attendance/check-out         — employee checks out for today
#   GET  /api/attendance/me                — employee views own attendance
#   GET  /api/attendance                   — admin views all attendance
#   GET  /api/attendance/<employee_id>     — admin views one employee's attendance
#
# Business rules:
#   - One check-in per employee per calendar day (enforced by DB unique constraint)
#   - Check-out requires an existing check-in for today
#   - Cannot check-out twice on the same day
#   - Status is set to 'present' on check-in; 'leave' is set by the leave approval flow
#   - Admin can view all records; employees can only see their own
# =============================================================================

import logging
from datetime import date, datetime, timedelta
from flask import Blueprint, request, jsonify
from flask_jwt_extended import jwt_required, get_jwt_identity

from extensions import db
from models.attendance import Attendance
from models.employee import Employee
from utils.auth_helpers import require_role

logger = logging.getLogger("dayflow")

# =============================================================================
# BLUEPRINT DEFINITION
# =============================================================================

attendance_bp = Blueprint("attendance", __name__)


# =============================================================================
# HELPER: get_employee_id_for_user
# =============================================================================

def get_employee_id_for_user(user_id):
    """
    Looks up the Employee.id for the given user_id.
    Returns the employee's primary key, or None if not found.

    Args:
        user_id (int): user primary key from the JWT

    Returns:
        int | None
    """
    emp = Employee.query.filter_by(user_id=user_id).first()
    return emp.id if emp else None


# =============================================================================
# ROUTE: POST /api/attendance/check-in
# =============================================================================

@attendance_bp.route("/check-in", methods=["POST"])
@jwt_required()
@require_role("employee", "admin")
def check_in():
    """
    Records today's check-in time for the authenticated employee.

    Steps:
        1. Resolve employee_id from JWT user_id
        2. Check no attendance record exists for today (prevent duplicate)
        3. Create Attendance row with check_in = current time, status = 'present'
        4. Commit and return the new record

    Success response (201):
        { attendance record }

    Error responses:
        404 — employee profile not found
        409 — already checked in today
        500 — database error
    """
    user_id = int(get_jwt_identity())
    today   = date.today()
    logger.debug("POST /check-in — user_id=%s date=%s", user_id, today)

    # ── Step 1: Resolve employee_id ───────────────────────────────────────────
    try:
        employee_id = get_employee_id_for_user(user_id)
    except Exception as exc:
        logger.error("check-in: DB error resolving employee for user_id=%s — %s", user_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee_id:
        logger.warning("check-in: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    # ── Step 2: Check for existing record today ───────────────────────────────
    try:
        existing = Attendance.query.filter_by(
            employee_id=employee_id,
            date=today
        ).first()
    except Exception as exc:
        logger.error("check-in: DB error checking existing record — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if existing:
        logger.warning(
            "check-in: duplicate check-in attempt — employee_id=%s date=%s",
            employee_id, today
        )
        return jsonify({"error": "Already checked in today"}), 409

    # ── Step 3: Create attendance record ──────────────────────────────────────
    now = datetime.utcnow().time()
    attendance = Attendance(
        employee_id=employee_id,
        date=today,
        check_in=now,
        status="present",
    )
    db.session.add(attendance)

    # ── Step 4: Commit ────────────────────────────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error("check-in: commit failed for employee_id=%s — %s", employee_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("check-in: success — employee_id=%s check_in=%s", employee_id, now)
    return jsonify(attendance.to_dict()), 201


# =============================================================================
# ROUTE: POST /api/attendance/check-out
# =============================================================================

@attendance_bp.route("/check-out", methods=["POST"])
@jwt_required()
@require_role("employee", "admin")
def check_out():
    """
    Records today's check-out time for the authenticated employee.

    Steps:
        1. Resolve employee_id from JWT user_id
        2. Find today's attendance record (must exist — check-in required first)
        3. Verify employee hasn't already checked out
        4. Set check_out = current time
        5. Commit and return the updated record

    Success response (200):
        { updated attendance record }

    Error responses:
        404 — employee profile not found or no check-in found for today
        409 — already checked out today
        500 — database error
    """
    user_id = int(get_jwt_identity())
    today   = date.today()
    logger.debug("POST /check-out — user_id=%s date=%s", user_id, today)

    # ── Step 1: Resolve employee_id ───────────────────────────────────────────
    try:
        employee_id = get_employee_id_for_user(user_id)
    except Exception as exc:
        logger.error("check-out: DB error resolving employee for user_id=%s — %s", user_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee_id:
        logger.warning("check-out: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    # ── Step 2: Find today's check-in record ──────────────────────────────────
    try:
        attendance = Attendance.query.filter_by(
            employee_id=employee_id,
            date=today
        ).first()
    except Exception as exc:
        logger.error("check-out: DB error fetching today's record — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not attendance:
        logger.warning(
            "check-out: no check-in found for employee_id=%s date=%s",
            employee_id, today
        )
        return jsonify({"error": "No check-in found for today — please check in first"}), 404

    # ── Step 3: Verify not already checked out ────────────────────────────────
    if attendance.check_out is not None:
        logger.warning(
            "check-out: already checked out — employee_id=%s date=%s",
            employee_id, today
        )
        return jsonify({"error": "Already checked out today"}), 409

    # ── Step 4: Set check-out time ────────────────────────────────────────────
    now = datetime.utcnow().time()
    attendance.check_out = now

    # ── Step 5: Commit ────────────────────────────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error("check-out: commit failed for employee_id=%s — %s", employee_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("check-out: success — employee_id=%s check_out=%s", employee_id, now)
    return jsonify(attendance.to_dict()), 200


# =============================================================================
# ROUTE: GET /api/attendance/me
# =============================================================================

@attendance_bp.route("/me", methods=["GET"])
@jwt_required()
@require_role("employee", "admin")
def get_my_attendance():
    """
    Returns the authenticated employee's own attendance records.

    Optional query parameters:
        week (bool, default false) — if 'true', return only the last 7 days
        month (int)                — filter by month number (1-12)
        year  (int)                — filter by year (e.g. 2026)

    Success response (200):
        { attendance: [ {...}, ... ], total: N }

    Error responses:
        404 — employee profile not found
        500 — database error
    """
    user_id = int(get_jwt_identity())
    logger.debug("GET /attendance/me — user_id=%s", user_id)

    # ── Resolve employee_id ───────────────────────────────────────────────────
    try:
        employee_id = get_employee_id_for_user(user_id)
    except Exception as exc:
        logger.error("GET /me: DB error — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee_id:
        logger.warning("GET /me: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    # ── Build query ───────────────────────────────────────────────────────────
    try:
        query = Attendance.query.filter_by(employee_id=employee_id)

        # ?week=true → last 7 days
        if request.args.get("week", "").lower() == "true":
            week_ago = date.today() - timedelta(days=6)
            query = query.filter(Attendance.date >= week_ago)
            logger.debug("GET /me: applying week filter from %s", week_ago)

        # ?month=8&year=2026 → specific month
        month = request.args.get("month")
        year  = request.args.get("year")
        if month:
            query = query.filter(db.extract("month", Attendance.date) == int(month))
        if year:
            query = query.filter(db.extract("year",  Attendance.date) == int(year))

        records = query.order_by(Attendance.date.desc()).all()

    except Exception as exc:
        logger.error("GET /me: query failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("GET /me: returning %d records for employee_id=%s", len(records), employee_id)
    return jsonify({
        "attendance": [r.to_dict() for r in records],
        "total":      len(records),
    }), 200


# =============================================================================
# ROUTE: GET /api/attendance  (admin only)
# =============================================================================

@attendance_bp.route("", methods=["GET"])
@jwt_required()
@require_role("admin")
def list_attendance():
    """
    Returns attendance records for all employees.
    Admin only.

    Optional query parameters:
        date        (str, YYYY-MM-DD) — filter by specific date
        employee_id (int)             — filter by one employee
        status      (str)             — filter by status (present/absent/leave/half-day)

    Success response (200):
        { attendance: [ {...}, ... ], total: N }

    Error responses:
        500 — database error
    """
    logger.debug("GET /api/attendance — admin list all")

    try:
        query = Attendance.query

        # Filter by date
        date_param = request.args.get("date")
        if date_param:
            try:
                filter_date = date.fromisoformat(date_param)
                query = query.filter(Attendance.date == filter_date)
                logger.debug("list_attendance: filtering by date=%s", filter_date)
            except ValueError:
                logger.warning("list_attendance: invalid date param '%s'", date_param)
                return jsonify({"error": "Invalid date format — use YYYY-MM-DD"}), 400

        # Filter by employee
        emp_id_param = request.args.get("employee_id")
        if emp_id_param:
            query = query.filter(Attendance.employee_id == int(emp_id_param))

        # Filter by status
        status_param = request.args.get("status")
        if status_param:
            query = query.filter(Attendance.status == status_param)

        records = query.order_by(Attendance.date.desc()).all()

    except Exception as exc:
        logger.error("list_attendance: query failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("list_attendance: returning %d records", len(records))
    return jsonify({
        "attendance": [r.to_dict() for r in records],
        "total":      len(records),
    }), 200


# =============================================================================
# ROUTE: GET /api/attendance/<employee_id>  (admin only)
# =============================================================================

@attendance_bp.route("/<int:employee_id>", methods=["GET"])
@jwt_required()
@require_role("admin")
def get_employee_attendance(employee_id):
    """
    Returns all attendance records for a specific employee.
    Admin only.

    Path parameter:
        employee_id (int) — Employee table primary key

    Optional query parameters:
        date   (str, YYYY-MM-DD) — filter by specific date
        status (str)             — filter by status

    Success response (200):
        { attendance: [ {...}, ... ], total: N }

    Error responses:
        404 — employee not found
        500 — database error
    """
    logger.debug("GET /api/attendance/%s — admin", employee_id)

    # ── Verify employee exists ────────────────────────────────────────────────
    try:
        employee = Employee.query.get(employee_id)
    except Exception as exc:
        logger.error("get_employee_attendance: DB error — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee:
        logger.warning("get_employee_attendance: employee_id=%s not found", employee_id)
        return jsonify({"error": "Employee not found"}), 404

    # ── Query attendance ──────────────────────────────────────────────────────
    try:
        query = Attendance.query.filter_by(employee_id=employee_id)

        date_param = request.args.get("date")
        if date_param:
            try:
                filter_date = date.fromisoformat(date_param)
                query = query.filter(Attendance.date == filter_date)
            except ValueError:
                return jsonify({"error": "Invalid date format — use YYYY-MM-DD"}), 400

        status_param = request.args.get("status")
        if status_param:
            query = query.filter(Attendance.status == status_param)

        records = query.order_by(Attendance.date.desc()).all()

    except Exception as exc:
        logger.error(
            "get_employee_attendance: query failed for employee_id=%s — %s",
            employee_id, exc, exc_info=True
        )
        return jsonify({"error": "Internal server error"}), 500

    logger.debug(
        "get_employee_attendance: returning %d records for employee_id=%s",
        len(records), employee_id
    )
    return jsonify({
        "attendance": [r.to_dict() for r in records],
        "total":      len(records),
    }), 200



