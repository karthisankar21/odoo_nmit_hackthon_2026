# =============================================================================
# blueprints/payroll.py
# -----------------------------------------------------------------------------
# Payroll blueprint for the Dayflow HRMS API.
#
# Endpoints:
#   GET /api/payroll/me                  — employee views own latest payroll
#   GET /api/payroll                     — admin views all employees' latest payroll
#   PUT /api/payroll/<employee_id>       — admin creates or updates payroll for an employee
#
# Business rules:
#   - Employees can only READ their own payroll — never write
#   - Admin can create or update any employee's payroll for a given month/year
#   - net_salary is ALWAYS computed server-side: basic + allowances - deductions
#     It is never accepted from the request body
#   - One payroll record per employee per month/year (DB unique constraint)
#   - PUT acts as an upsert: creates if not exists, updates if exists
# =============================================================================

import logging
from flask import Blueprint, request, jsonify
from flask_jwt_extended import jwt_required, get_jwt_identity

from extensions import db
from models.payroll import Payroll
from models.employee import Employee
from utils.auth_helpers import require_role

logger = logging.getLogger("dayflow")

# =============================================================================
# BLUEPRINT DEFINITION
# =============================================================================

payroll_bp = Blueprint("payroll", __name__)


# =============================================================================
# HELPER: get_employee_id_for_user
# =============================================================================

def get_employee_id_for_user(user_id):
    """
    Returns Employee.id for the given user_id, or None if not found.

    Args:
        user_id (int): user primary key from the JWT

    Returns:
        int | None
    """
    emp = Employee.query.filter_by(user_id=user_id).first()
    return emp.id if emp else None


# =============================================================================
# HELPER: get_latest_payroll
# =============================================================================

def get_latest_payroll(employee_id):
    """
    Returns the most recent Payroll record for an employee,
    ordered by year DESC then month DESC.

    Args:
        employee_id (int): Employee primary key

    Returns:
        Payroll | None
    """
    return (
        Payroll.query
        .filter_by(employee_id=employee_id)
        .order_by(Payroll.year.desc(), Payroll.month.desc())
        .first()
    )


# =============================================================================
# ROUTE: GET /api/payroll/me
# =============================================================================

@payroll_bp.route("/me", methods=["GET"])
@jwt_required()
@require_role("employee", "admin")
def get_my_payroll():
    """
    Returns the authenticated employee's most recent payroll record.

    The most recent record is the one with the highest year, then month.

    Success response (200):
        { payroll record }

    Error responses:
        404 — employee profile not found, or no payroll record exists yet
        500 — database error
    """
    user_id = int(get_jwt_identity())
    logger.debug("GET /api/payroll/me — user_id=%s", user_id)

    # ── Step 1: Resolve employee_id ───────────────────────────────────────────
    try:
        employee_id = get_employee_id_for_user(user_id)
    except Exception as exc:
        logger.error("get_my_payroll: DB error resolving employee — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee_id:
        logger.warning("get_my_payroll: no employee record for user_id=%s", user_id)
        return jsonify({"error": "Employee profile not found"}), 404

    # ── Step 2: Fetch latest payroll record ───────────────────────────────────
    try:
        payroll = get_latest_payroll(employee_id)
    except Exception as exc:
        logger.error("get_my_payroll: DB error — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not payroll:
        logger.warning("get_my_payroll: no payroll records found for employee_id=%s", employee_id)
        # Return empty list instead of 404 — frontend shows "no records yet" state
        return jsonify({"payrolls": [], "total": 0}), 200

    logger.debug(
        "get_my_payroll: returning payroll id=%s %s/%s for employee_id=%s",
        payroll.id, payroll.month, payroll.year, employee_id
    )
    return jsonify({"payrolls": [payroll.to_dict()], "total": 1}), 200


# =============================================================================
# ROUTE: GET /api/payroll  (admin only)
# =============================================================================

@payroll_bp.route("", methods=["GET"])
@jwt_required()
@require_role("admin")
def list_payroll():
    """
    Returns the latest payroll record for every employee.
    Admin only.

    For each employee, only the most recent month/year record is returned,
    giving a clean summary table for the admin dashboard.

    Optional query parameters:
        month (int) — filter by specific month (1-12)
        year  (int) — filter by specific year (e.g. 2026)

    Success response (200):
        { payroll: [ {...}, ... ], total: N }

    Error responses:
        500 — database error
    """
    logger.debug("GET /api/payroll — admin list all")

    try:
        query = Payroll.query

        # Optional month/year filters
        month_param = request.args.get("month")
        year_param  = request.args.get("year")

        if month_param:
            query = query.filter(Payroll.month == int(month_param))
            logger.debug("list_payroll: filtering by month=%s", month_param)

        if year_param:
            query = query.filter(Payroll.year == int(year_param))
            logger.debug("list_payroll: filtering by year=%s", year_param)

        # Order by most recent first
        records = query.order_by(
            Payroll.year.desc(),
            Payroll.month.desc()
        ).all()

    except Exception as exc:
        logger.error("list_payroll: query failed — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    logger.debug("list_payroll: returning %d records", len(records))
    return jsonify({
        "payrolls": [r.to_dict() for r in records],
        "total":    len(records),
    }), 200


# =============================================================================
# ROUTE: PUT /api/payroll/<employee_id>  (admin only)
# =============================================================================

@payroll_bp.route("/<int:employee_id>", methods=["PUT"])
@jwt_required()
@require_role("admin")
def upsert_payroll(employee_id):
    """
    Creates or updates a payroll record for an employee (upsert).
    Admin only.

    If a record already exists for the given employee + month + year,
    it is updated. Otherwise a new record is created.

    net_salary is ALWAYS computed server-side:
        net_salary = basic_salary + allowances - deductions
    Any net_salary value sent in the request body is silently ignored.

    Path parameter:
        employee_id (int) — Employee table primary key

    Request body (JSON):
        basic_salary (float, required) — base monthly salary
        allowances   (float, required) — additional allowances
        deductions   (float, required) — deductions (tax, loans, etc.)
        month        (int,   required) — 1–12
        year         (int,   required) — e.g. 2026

    Success response (200 if updated, 201 if created):
        { payroll record with computed net_salary }

    Error responses:
        400 — missing or invalid fields
        404 — employee not found
        500 — database error
    """
    admin_user_id = int(get_jwt_identity())
    logger.debug(
        "PUT /api/payroll/%s — by admin user_id=%s", employee_id, admin_user_id
    )

    # ── Step 1: Verify employee exists ────────────────────────────────────────
    try:
        employee = Employee.query.get(employee_id)
    except Exception as exc:
        logger.error("upsert_payroll: DB error checking employee_id=%s — %s", employee_id, exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    if not employee:
        logger.warning("upsert_payroll: employee_id=%s not found", employee_id)
        return jsonify({"error": "Employee not found"}), 404

    # ── Step 2: Parse and validate request body ───────────────────────────────
    data = request.get_json()
    if not data:
        logger.warning("upsert_payroll: no JSON body for employee_id=%s", employee_id)
        return jsonify({"error": "Request body is required"}), 400

    # Validate all required fields are present
    required = ["basic_salary", "allowances", "deductions", "month", "year"]
    missing  = [f for f in required if f not in data]
    if missing:
        logger.warning("upsert_payroll: missing fields %s for employee_id=%s", missing, employee_id)
        return jsonify({"error": f"Missing required fields: {', '.join(missing)}"}), 400

    # Parse and validate numeric values
    try:
        basic_salary = float(data["basic_salary"])
        allowances   = float(data["allowances"])
        deductions   = float(data["deductions"])
        month        = int(data["month"])
        year         = int(data["year"])
    except (ValueError, TypeError) as exc:
        logger.warning(
            "upsert_payroll: invalid numeric values for employee_id=%s — %s",
            employee_id, exc
        )
        return jsonify({"error": "basic_salary, allowances, deductions, month, year must be numbers"}), 400

    # Validate month range
    if not (1 <= month <= 12):
        logger.warning("upsert_payroll: invalid month=%s for employee_id=%s", month, employee_id)
        return jsonify({"error": "month must be between 1 and 12"}), 400

    # Validate non-negative salary values
    if any(v < 0 for v in [basic_salary, allowances, deductions]):
        logger.warning("upsert_payroll: negative value for employee_id=%s", employee_id)
        return jsonify({"error": "Salary values cannot be negative"}), 400

    # ── Step 3: Look up existing record for this employee + month + year ──────
    try:
        existing = Payroll.query.filter_by(
            employee_id=employee_id,
            month=month,
            year=year,
        ).first()
    except Exception as exc:
        logger.error("upsert_payroll: DB error checking existing record — %s", exc, exc_info=True)
        return jsonify({"error": "Internal server error"}), 500

    # ── Step 4: Upsert — update or create ─────────────────────────────────────
    is_new = existing is None

    if existing:
        # UPDATE existing record
        logger.debug(
            "upsert_payroll: updating existing record id=%s for employee_id=%s %s/%s",
            existing.id, employee_id, month, year
        )
        existing.basic_salary = basic_salary
        existing.allowances   = allowances
        existing.deductions   = deductions
        payroll = existing

    else:
        # CREATE new record
        logger.debug(
            "upsert_payroll: creating new record for employee_id=%s %s/%s",
            employee_id, month, year
        )
        payroll = Payroll(
            employee_id=employee_id,
            basic_salary=basic_salary,
            allowances=allowances,
            deductions=deductions,
            month=month,
            year=year,
        )
        db.session.add(payroll)

    # ── Step 5: Compute net_salary server-side ────────────────────────────────
    # This MUST happen after setting the salary components above.
    # net_salary from the client is ignored — always recomputed here.
    payroll.compute_net()
    logger.debug(
        "upsert_payroll: computed net_salary=%.2f "
        "(basic=%.2f + allowances=%.2f - deductions=%.2f)",
        float(payroll.net_salary), basic_salary, allowances, deductions
    )

    # ── Step 6: Commit ────────────────────────────────────────────────────────
    try:
        db.session.commit()
    except Exception as exc:
        db.session.rollback()
        logger.error(
            "upsert_payroll: commit failed for employee_id=%s — %s",
            employee_id, exc, exc_info=True
        )
        return jsonify({"error": "Internal server error"}), 500

    http_status = 201 if is_new else 200
    logger.debug(
        "upsert_payroll: success — employee_id=%s %s/%s status=%s",
        employee_id, month, year, http_status
    )
    return jsonify(payroll.to_dict()), http_status
