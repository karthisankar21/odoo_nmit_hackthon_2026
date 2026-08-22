# =============================================================================
# blueprints/analytics.py
# -----------------------------------------------------------------------------
# Analytics blueprint for the Dayflow HRMS API.
#
# Endpoints:
#   GET /api/analytics/summary  — admin dashboard summary (real DB data)
#
# What it returns:
#   - Total employee count
#   - Today's attendance breakdown (present / absent / leave / half-day)
#   - Count of pending leave requests
#   - Breakdown of approved leave requests by type (paid/sick/unpaid)
#   - Employee headcount grouped by department
#
# Access: admin only
# All data is queried live — no caching, no mocks.
# =============================================================================

import logging
from datetime import date
from flask import Blueprint, jsonify
from flask_jwt_extended import jwt_required, get_jwt_identity
from sqlalchemy import func

from extensions import db
from models.employee import Employee
from models.attendance import Attendance
from models.leave_request import LeaveRequest
from utils.auth_helpers import require_role

logger = logging.getLogger("dayflow")

# =============================================================================
# BLUEPRINT DEFINITION
# =============================================================================

analytics_bp = Blueprint("analytics", __name__)


# =============================================================================
# ROUTE: GET /api/analytics/summary  (admin only)
# =============================================================================

@analytics_bp.route("/summary", methods=["GET"])
@jwt_required()
@require_role("admin")
def summary():
    """
    Returns a single JSON object with all key HR metrics for the admin dashboard.
    Admin only.

    Queries performed:
        1. total_employees      — COUNT of all Employee rows
        2. attendance_today     — today's Attendance rows grouped by status
        3. present_today        — employees with status='present' today
        4. on_leave_today       — employees with status='leave' today
        5. absent_today         — employees with status='absent' today
        6. half_day_today       — employees with status='half-day' today
        7. pending_leave_requests — LeaveRequests where status='pending'
        8. leave_by_type        — approved LeaveRequests grouped by leave_type
        9. department_headcount — Employee rows grouped by department

    Success response (200):
    {
        "total_employees":        10,
        "present_today":           7,
        "on_leave_today":          2,
        "absent_today":            1,
        "half_day_today":          0,
        "pending_leave_requests":  3,
        "leave_by_type": {
            "paid":   1,
            "sick":   1,
            "unpaid": 0
        },
        "department_headcount": {
            "Tech":    5,
            "HR":      2,
            "Finance": 3
        }
    }

    Error responses:
        500 — database error
    """
    admin_user_id = int(get_jwt_identity())
    today = date.today()
    logger.debug("GET /api/analytics/summary — admin user_id=%s date=%s", admin_user_id, today)

    # ==========================================================================
    # QUERY 1: Total employee count
    # ==========================================================================
    try:
        total_employees = Employee.query.count()
        logger.debug("analytics: total_employees=%s", total_employees)
    except Exception as exc:
        logger.error("analytics: failed to count employees — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ==========================================================================
    # QUERY 2: Today's attendance grouped by status
    # Returns a list of (status, count) tuples
    # ==========================================================================
    try:
        attendance_rows = (
            db.session.query(Attendance.status, func.count(Attendance.id))
            .filter(Attendance.date == today)
            .group_by(Attendance.status)
            .all()
        )
        # Convert to a dict: { 'present': 7, 'leave': 2, ... }
        attendance_by_status = {row[0]: row[1] for row in attendance_rows}
        logger.debug("analytics: attendance_by_status=%s", attendance_by_status)
    except Exception as exc:
        logger.error("analytics: failed to query today's attendance — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # Extract individual counts — default to 0 if no records for that status
    present_today  = attendance_by_status.get("present",  0)
    on_leave_today = attendance_by_status.get("leave",    0)
    absent_today   = attendance_by_status.get("absent",   0)
    half_day_today = attendance_by_status.get("half-day", 0)

    # ==========================================================================
    # QUERY 3: Pending leave requests count
    # ==========================================================================
    try:
        pending_leave_requests = (
            LeaveRequest.query
            .filter_by(status="pending")
            .count()
        )
        logger.debug("analytics: pending_leave_requests=%s", pending_leave_requests)
    except Exception as exc:
        logger.error("analytics: failed to count pending leaves — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ==========================================================================
    # QUERY 4: Approved leave requests grouped by type
    # Gives a breakdown of how many paid/sick/unpaid leaves are approved overall
    # ==========================================================================
    try:
        leave_type_rows = (
            db.session.query(LeaveRequest.leave_type, func.count(LeaveRequest.id))
            .filter(LeaveRequest.status == "approved")
            .group_by(LeaveRequest.leave_type)
            .all()
        )
        # Start with all types at 0 so the response always has all three keys
        leave_by_type = {"paid": 0, "sick": 0, "unpaid": 0}
        for leave_type, count in leave_type_rows:
            leave_by_type[leave_type] = count
        logger.debug("analytics: leave_by_type=%s", leave_by_type)
    except Exception as exc:
        logger.error("analytics: failed to query leave by type — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ==========================================================================
    # QUERY 5: Employee headcount grouped by department
    # NULL department is grouped under 'Unassigned'
    # ==========================================================================
    try:
        dept_rows = (
            db.session.query(Employee.department, func.count(Employee.id))
            .group_by(Employee.department)
            .all()
        )
        department_headcount = {}
        for dept, count in dept_rows:
            # Use 'Unassigned' label for employees with no department set
            key = dept if dept else "Unassigned"
            department_headcount[key] = count
        logger.debug("analytics: department_headcount=%s", department_headcount)
    except Exception as exc:
        logger.error("analytics: failed to query department headcount — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ==========================================================================
    # BUILD AND RETURN RESPONSE
    # ==========================================================================
    response = {
        "total_employees":        total_employees,
        "present_today":          present_today,
        "on_leave_today":         on_leave_today,
        "absent_today":           absent_today,
        "half_day_today":         half_day_today,
        "pending_leave_requests": pending_leave_requests,
        "leave_by_type":          leave_by_type,
        "department_headcount":   department_headcount,
    }

    logger.debug("analytics: summary built successfully")
    return jsonify(response), 200
