# =============================================================================
# models/attendance.py
# -----------------------------------------------------------------------------
# Tracks daily attendance for each employee.
# One row per employee per calendar day.
# Employees check in and check out; status is derived from those actions.
# Admin can also manually set status (e.g. 'absent', 'half-day').
# =============================================================================

from extensions import db


class Attendance(db.Model):
    """
    Table: attendance
    One row per employee per day.
    Unique constraint on (employee_id, date) prevents duplicate records.

    Status values (from PDF §3.4.1):
        present  → employee checked in (and optionally checked out)
        absent   → no check-in, marked absent by admin
        half-day → worked partial day
        leave    → auto-set when a leave request is approved for this date
    """

    __tablename__ = "attendance"

    # ── Primary key ───────────────────────────────────────────────────────────
    id = db.Column(db.Integer, primary_key=True)

    # ── Foreign key → employees ───────────────────────────────────────────────
    employee_id = db.Column(
        db.Integer,
        db.ForeignKey("employees.id", ondelete="CASCADE"),
        nullable=False,
    )

    # ── Date of attendance ────────────────────────────────────────────────────
    # Stored as DATE (no time component) for easy daily querying
    date = db.Column(db.Date, nullable=False)

    # ── Check-in / check-out times ────────────────────────────────────────────
    # Both are nullable:
    #   check_in=None  → employee has not checked in yet
    #   check_out=None → employee has checked in but not yet checked out
    check_in  = db.Column(db.Time, nullable=True)
    check_out = db.Column(db.Time, nullable=True)

    # ── Attendance status ─────────────────────────────────────────────────────
    # Allowed values: 'present' | 'absent' | 'half-day' | 'leave'
    status = db.Column(db.String(20), nullable=False, default="present")

    # ── Timestamps ────────────────────────────────────────────────────────────
    created_at = db.Column(db.DateTime, server_default=db.func.now())

    # ── Table-level constraints ───────────────────────────────────────────────
    # One record per employee per day — enforced at database level
    __table_args__ = (
        db.UniqueConstraint(
            "employee_id", "date",
            name="uq_attendance_employee_date"
        ),
    )

    # ── Relationships ─────────────────────────────────────────────────────────
    employee = db.relationship("Employee", back_populates="attendances")

    # ── Helper methods ────────────────────────────────────────────────────────

    def to_dict(self):
        """
        Serialises the attendance record to a dict for API responses.
        Times are formatted as HH:MM strings; date as YYYY-MM-DD.
        """
        return {
            "id":          self.id,
            "employee_id": self.employee_id,
            "date":        self.date.isoformat()      if self.date      else None,
            "check_in":    self.check_in.strftime("%H:%M")  if self.check_in  else None,
            "check_out":   self.check_out.strftime("%H:%M") if self.check_out else None,
            "status":      self.status,
        }

    def __repr__(self):
        return (
            f"<Attendance employee_id={self.employee_id} "
            f"date={self.date} status={self.status}>"
        )
