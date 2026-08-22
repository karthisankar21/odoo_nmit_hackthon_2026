# =============================================================================
# models/leave_request.py
# -----------------------------------------------------------------------------
# Stores leave applications submitted by employees.
# An employee picks a leave type, date range, and optional remarks.
# An admin/HR officer then approves or rejects the request.
# When approved, the attendance rows for those dates are auto-set to 'leave'.
# =============================================================================

from extensions import db


class LeaveRequest(db.Model):
    """
    Table: leave_requests
    One row per leave application.

    Leave types (from PDF §3.5.1):
        paid    → paid annual leave
        sick    → sick/medical leave
        unpaid  → unpaid leave

    Status values (from PDF §3.5.1):
        pending  → just submitted, waiting for admin action
        approved → admin approved; attendance rows set to 'leave'
        rejected → admin rejected; attendance unchanged
    """

    __tablename__ = "leave_requests"

    # ── Primary key ───────────────────────────────────────────────────────────
    id = db.Column(db.Integer, primary_key=True)

    # ── Foreign key → employees ───────────────────────────────────────────────
    employee_id = db.Column(
        db.Integer,
        db.ForeignKey("employees.id", ondelete="CASCADE"),
        nullable=False,
    )

    # ── Leave details (set by employee) ──────────────────────────────────────
    # leave_type: 'paid' | 'sick' | 'unpaid'
    leave_type = db.Column(db.String(20), nullable=False)
    start_date = db.Column(db.Date, nullable=False)
    end_date   = db.Column(db.Date, nullable=False)
    remarks    = db.Column(db.Text, nullable=True)   # optional reason from employee

    # ── Approval fields (set by admin) ────────────────────────────────────────
    # status: 'pending' | 'approved' | 'rejected'
    status        = db.Column(db.String(20), nullable=False, default="pending")
    admin_comment = db.Column(db.Text, nullable=True)  # optional note from admin

    # ── Timestamps ────────────────────────────────────────────────────────────
    created_at = db.Column(db.DateTime, server_default=db.func.now())
    updated_at = db.Column(
        db.DateTime, server_default=db.func.now(), onupdate=db.func.now()
    )

    # ── Relationships ─────────────────────────────────────────────────────────
    employee = db.relationship("Employee", back_populates="leave_requests")

    # ── Helper methods ────────────────────────────────────────────────────────

    def to_dict(self):
        """
        Serialises the leave request to a dict for API responses.
        Includes the employee's name for admin list views.
        """
        return {
            "id":            self.id,
            "employee_id":   self.employee_id,
            "employee_name": self.employee.user.name if self.employee and self.employee.user else None,
            "leave_type":    self.leave_type,
            "start_date":    self.start_date.isoformat() if self.start_date else None,
            "end_date":      self.end_date.isoformat()   if self.end_date   else None,
            "remarks":       self.remarks,
            "status":        self.status,
            "admin_comment": self.admin_comment,
            "created_at":    self.created_at.isoformat() if self.created_at else None,
        }

    def __repr__(self):
        return (
            f"<LeaveRequest id={self.id} employee_id={self.employee_id} "
            f"type={self.leave_type} status={self.status}>"
        )
