# =============================================================================
# blueprints/leave.py
# -----------------------------------------------------------------------------
# Leave management blueprint for the Dayflow HRMS API.
#
# Endpoints:
#   POST /api/leave                    — employee submits a leave request
#   GET  /api/leave/me                 — employee views own leave history
#   GET  /api/leave                    — admin views all leave requests
#   PUT  /api/leave/<id>/approve       — admin approves a leave request
#   PUT  /api/leave/<id>/reject        — admin rejects a leave request
#
# Business rules:
#   - start_date must be <= end_date
#   - Only 'paid', 'sick', 'unpaid' are valid leave types
#   - New requests always start as 'pending'
#   - Approving auto-creates/updates Attendance rows for each day in range
#     with status='leave', preserving any existing check-in/out times
#   - Rejecting only updates the leave request — attendance is unchanged
#   - Only the owning employee can see their own leave; admin sees all
# =============================================================================

import logging
from datetime import date, timedelta
from flask import Blueprint, request, jsonify
from flask_jwt_extended import jwt_required, get_jwt_identity

from extensions import db
from models.leave_request import LeaveRequest
from models.attendance import Attendance
from models.employee import Employee
from utils.auth_helpers import require_role

logger = logging.getLogger("dayflow")

# =============================================================================
# BLUEPRINT DEFINITION
# =============================================================================

leave_bp = Blueprint("leave", __name__)

# Valid values — checked at request time, not only at DB level
VALID_LEAVE_TYPES = {"paid", "sick", "unpaid"}


# =============================================================================
# HELPER: get_employee_id_for_user
# =============================================================================


def get_employee_id_for_user(user_id):
    """
    Returns the Employee.id for the given user_id, or None if not found.

    Args:
        user_id (int): user primary key from the JWT

    Returns:
        int | None
    """
    emp = Employee.query.filter_by(user_id=user_id).first()
    return emp.id if emp else None


# =============================================================================
# HELPER: upsert_attendance_leave
# =============================================================================


def upsert_attendance_leave(employee_id, target_date):
    """
    Creates or updates the Attendance row for a specific employee + date,
    setting status = 'leave'.

    Called for every calendar day in an approved leave date range.
    If a row already exists (e.g. employee checked in), status is updated
    to 'leave' but check_in / check_out times are preserved.

    Args:
        employee_id (int): Employee primary key
        target_date (date): the calendar date to mark as leave
    """
    existing = Attendance.query.filter_by(
        employee_id=employee_id, date=target_date
    ).first()

    if existing:
        # Row exists — just flip the status to 'leave'
        existing.status = "leave"
        logger.debug(
            "upsert_attendance_leave: updated existing row — employee_id=%s date=%s",
            employee_id,
            target_date,
        )
    else:
        # No row yet — create one with status='leave' and no check-in/out
        new_row = Attendance(
            employee_id=employee_id,
            date=target_date,
            status="leave",
        )
        db.session.add(new_row)
        logger.debug(
            "upsert_attendance_leave: created new row — employee_id=%s date=%s",
            employee_id,
            target_date,
        )


# =============================================================================
# ROUTE: POST /api/leave
# =============================================================================


@leave_bp.route("", methods=["POST"])
@jwt_required()
@require_role("employee", "admin")
def apply_leave():
    """
    Submit a new leave request.

    Request body (JSON):
        leave_type (str, required) — 'paid' | 'sick' | 'unpaid'
        start_date (str, required) — YYYY-MM-DD
        end_date   (str, required) — YYYY-MM-DD (must be >= start_date)
        remarks    (str, optional) — reason for the leave

    Success response (201):
        { leave request record }

    Error responses:
        400 — missing fields, invalid date format, end before start, invalid type
        404 — employee profile not found
        500 — database error
    """
    user_id = int(get_jwt_identity())
    logger.debug("POST /api/leave — user_id=%s", user_id)

    # ── Step 1: Parse request body ────────────────────────────────────────────
    data = request.get_json()
    if not data:
        logger.warning("apply_leave: no JSON body for user_id=%s", user_id)
        return jsonify({"error": "Request body is required"}), 400

    leave_type = data.get("leave_type", "").strip().lower()
    start_str = data.get("start_date", "").strip()
    end_str = data.get("end_date", "").strip()
    remarks = (data.get("remarks") or "").strip() or None

    # ── Step 2: Validate leave type ───────────────────────────────────────────
    if leave_type not in VALID_LEAVE_TYPES:
        logger.warning(
            "apply_leave: invalid leave_type='%s' from user_id=%s", leave_type, user_id
        )
        return (
            jsonify(
                {"error": f"leave_type must be one of: {', '.join(VALID_LEAVE_TYPES)}"}
            ),
            400,
        )

    # ── Step 3: Validate and parse dates ──────────────────────────────────────
    if not start_str or not end_str:
        logger.warning("apply_leave: missing dates from user_id=%s", user_id)
        return jsonify({"error": "start_date and end_date are required"}), 400

    try:
        start_date = date.fromisoformat(start_str)
        end_date = date.fromisoformat(end_str)
    except ValueError:
        logger.warning(
            "apply_leave: invalid date format start='%s' end='%s' from user_id=%s",
            start_str,
            end_str,
            user_id,
        )
        return jsonify({"error": "Invalid date format — use YYYY-MM-DD"}), 400

    # ── Step 4: Validate date range ───────────────────────────────────────────
    if end_date < start_date:
        logger.warning(
            "apply_leave: end_date %s < start_date %s from user_id=%s",
            end_date,
            start_date,
            user_id,
        )
        return jsonify({"error": "end_date must be on or after start_date"}), 400

    # ── Step 5: Resolve employee_id ───────────────────────────────────────────
    try:
        employee_id = get_employee_id_for_user(user_id)
    except Exception as exc:
        logger.error(
            "apply_leave: DB error resolving employee — %s", exc, exc_info=True
        )
        return jsonify({"error": "Internal server error"}), 500

    if not employee_id:
        logger.warning("apply_leave: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    logger.debug(
        "apply_leave: employee_id=%s type=%s %s→%s",
        employee_id,
        leave_type,
        start_date,
        end_date,
    )

    # ── Step 6: Create leave request ──────────────────────────────────────────
    leave_req = LeaveRequest(
        employee_id=employee_id,
        leave_type=leave_type,
        start_date=start_date,
        end_date=end_date,
        remarks=remarks,
        status="pending",
    )
    db.session.add(leave_req)

    # ── Step 7: Commit ────────────────────────────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error("apply_leave: commit failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("apply_leave: created leave_request id=%s", leave_req.id)
    return jsonify(leave_req.to_dict()), 201


# =============================================================================
# ROUTE: GET /api/leave/me
# =============================================================================


@leave_bp.route("/me", methods=["GET"])
@jwt_required()
@require_role("employee", "admin")
def get_my_leave():
    """
    Returns the authenticated employee's own leave request history.

    Optional query parameters:
        status (str) — filter by status: 'pending' | 'approved' | 'rejected'

    Success response (200):
        { leave_requests: [ {...}, ... ], total: N }

    Error responses:
        404 — employee profile not found
        500 — database error
    """
    user_id = int(get_jwt_identity())
    logger.debug("GET /api/leave/me — user_id=%s", user_id)

    # ── Resolve employee_id ───────────────────────────────────────────────────
    try:
        employee_id = get_employee_id_for_user(user_id)
    except Exception as exc:
        logger.error("get_my_leave: DB error — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee_id:
        logger.warning("get_my_leave: no employee for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    # ── Build query with optional status filter ───────────────────────────────
    try:
        query = LeaveRequest.query.filter_by(employee_id=employee_id)

        status_param = request.args.get("status", "").strip().lower()
        if status_param:
            query = query.filter(LeaveRequest.status == status_param)
            logger.debug("get_my_leave: filtering by status=%s", status_param)

        records = query.order_by(LeaveRequest.created_at.desc()).all()

    except Exception as exc:
        logger.error("get_my_leave: query failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug(
        "get_my_leave: returning %d records for employee_id=%s",
        len(records),
        employee_id,
    )
    return (
        jsonify(
            {
                "leave_requests": [r.to_dict() for r in records],
                "total": len(records),
            }
        ),
        200,
    )


# =============================================================================
# ROUTE: GET /api/leave  (admin only)
# =============================================================================


@leave_bp.route("", methods=["GET"])
@jwt_required()
@require_role("admin")
def list_leave():
    """
    Returns all leave requests across all employees.
    Admin only.

    Optional query parameters:
        status      (str) — filter by status: pending | approved | rejected
        employee_id (int) — filter by one employee
        leave_type  (str) — filter by leave type: paid | sick | unpaid

    Success response (200):
        { leave_requests: [ {...}, ... ], total: N }

    Error responses:
        500 — database error
    """
    logger.debug("GET /api/leave — admin list all")

    try:
        query = LeaveRequest.query

        # Optional filters
        status_param = request.args.get("status", "").strip().lower()
        if status_param:
            query = query.filter(LeaveRequest.status == status_param)
            logger.debug("list_leave: filtering by status=%s", status_param)

        emp_id_param = request.args.get("employee_id")
        if emp_id_param:
            query = query.filter(LeaveRequest.employee_id == int(emp_id_param))

        type_param = request.args.get("leave_type", "").strip().lower()
        if type_param:
            query = query.filter(LeaveRequest.leave_type == type_param)

        records = query.order_by(LeaveRequest.created_at.desc()).all()

    except Exception as exc:
        logger.error("list_leave: query failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("list_leave: returning %d records", len(records))
    return (
        jsonify(
            {
                "leave_requests": [r.to_dict() for r in records],
                "total": len(records),
            }
        ),
        200,
    )


# =============================================================================
# ROUTE: PUT /api/leave/<id>/approve  (admin only)
# =============================================================================


@leave_bp.route("/<int:leave_id>/approve", methods=["PUT"])
@jwt_required()
@require_role("admin")
def approve_leave(leave_id):
    """
    Approves a pending leave request.
    Admin only.

    Steps:
        1. Fetch the leave request by id
        2. Set status = 'approved', save optional admin_comment
        3. Loop every calendar day in the date range
           → upsert Attendance row with status = 'leave'
        4. Commit all changes atomically

    Request body (JSON, optional):
        admin_comment (str) — note from admin to employee

    Path parameter:
        leave_id (int) — LeaveRequest primary key

    Success response (200):
        { updated leave request }

    Error responses:
        404 — leave request not found
        400 — leave request is not in 'pending' status
        500 — database error
    """
    admin_user_id = int(get_jwt_identity())
    logger.debug(
        "PUT /api/leave/%s/approve — by admin user_id=%s", leave_id, admin_user_id
    )

    # ── Step 1: Fetch leave request ───────────────────────────────────────────
    try:
        leave_req = LeaveRequest.query.get(leave_id)
    except Exception as exc:
        logger.error(
            "approve_leave: DB error fetching id=%s — %s", leave_id, exc, exc_info=True
        )
        return jsonify({"error": "Internal server error"}), 500

    if not leave_req:
        logger.warning("approve_leave: leave_id=%s not found", leave_id)
        return jsonify({"error": "Leave request not found"}), 404

    # ── Step 2: Only pending requests can be approved ─────────────────────────
    if leave_req.status != "pending":
        logger.warning(
            "approve_leave: leave_id=%s is already '%s' — cannot approve",
            leave_id,
            leave_req.status,
        )
        return (
            jsonify(
                {
                    "error": f"Leave request is already '{leave_req.status}' — only pending requests can be approved"
                }
            ),
            400,
        )

    # ── Step 3: Update leave request ──────────────────────────────────────────
    data = request.get_json() or {}
    leave_req.status = "approved"
    leave_req.admin_comment = data.get("admin_comment", "").strip() or None

    logger.debug(
        "approve_leave: approving leave_id=%s employee_id=%s %s→%s",
        leave_id,
        leave_req.employee_id,
        leave_req.start_date,
        leave_req.end_date,
    )

    # ── Step 4: Mark attendance as 'leave' for each day in range ─────────────
    # Iterate from start_date to end_date inclusive
    current_day = leave_req.start_date
    days_marked = 0

    while current_day <= leave_req.end_date:
        upsert_attendance_leave(leave_req.employee_id, current_day)
        current_day += timedelta(days=1)
        days_marked += 1

    logger.debug(
        "approve_leave: marked %d attendance days as 'leave' for employee_id=%s",
        days_marked,
        leave_req.employee_id,
    )

    # ── Step 5: Commit all changes atomically ─────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error(
            "approve_leave: commit failed for leave_id=%s — %s",
            leave_id,
            exc,
            exc_info=True,
        )
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("approve_leave: success — leave_id=%s", leave_id)
    return jsonify(leave_req.to_dict()), 200


# =============================================================================
# ROUTE: PUT /api/leave/<id>/reject  (admin only)
# =============================================================================


@leave_bp.route("/<int:leave_id>/reject", methods=["PUT"])
@jwt_required()
@require_role("admin")
def reject_leave(leave_id):
    """
    Rejects a pending leave request.
    Admin only.

    Steps:
        1. Fetch the leave request by id
        2. Set status = 'rejected', save optional admin_comment
        3. Commit — attendance rows are NOT modified on rejection

    Request body (JSON, optional):
        admin_comment (str) — reason for rejection

    Path parameter:
        leave_id (int) — LeaveRequest primary key

    Success response (200):
        { updated leave request }

    Error responses:
        404 — leave request not found
        400 — leave request is not in 'pending' status
        500 — database error
    """
    admin_user_id = int(get_jwt_identity())
    logger.debug(
        "PUT /api/leave/%s/reject — by admin user_id=%s", leave_id, admin_user_id
    )

    # ── Step 1: Fetch leave request ───────────────────────────────────────────
    try:
        leave_req = LeaveRequest.query.get(leave_id)
    except Exception as exc:
        logger.error(
            "reject_leave: DB error fetching id=%s — %s", leave_id, exc, exc_info=True
        )
        return jsonify({"error": "Internal server error"}), 500

    if not leave_req:
        logger.warning("reject_leave: leave_id=%s not found", leave_id)
        return jsonify({"error": "Leave request not found"}), 404

    # ── Step 2: Only pending requests can be rejected ─────────────────────────
    if leave_req.status != "pending":
        logger.warning(
            "reject_leave: leave_id=%s is already '%s' — cannot reject",
            leave_id,
            leave_req.status,
        )
        return (
            jsonify(
                {
                    "error": f"Leave request is already '{leave_req.status}' — only pending requests can be rejected"
                }
            ),
            400,
        )

    # ── Step 3: Update leave request ──────────────────────────────────────────
    data = request.get_json() or {}
    leave_req.status = "rejected"
    leave_req.admin_comment = data.get("admin_comment", "").strip() or None

    logger.debug(
        "reject_leave: rejecting leave_id=%s employee_id=%s",
        leave_id,
        leave_req.employee_id,
    )

    # ── Step 4: Commit ────────────────────────────────────────────────────────
    # Note: attendance rows are NOT changed on rejection
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error(
            "reject_leave: commit failed for leave_id=%s — %s",
            leave_id,
            exc,
            exc_info=True,
        )
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("reject_leave: success — leave_id=%s", leave_id)
    return jsonify(leave_req.to_dict()), 200
