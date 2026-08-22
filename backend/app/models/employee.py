# =============================================================================
# models/employee.py
# -----------------------------------------------------------------------------
# Stores HR-specific profile data for each employee.
# Every User with role='employee' (and role='admin') gets one Employee record
# created automatically at registration.
# Linked to User via user_id (one-to-one).
# =============================================================================

from extensions import db


class Employee(db.Model):
    """
    Table: employees
    Holds personal and job details for each staff member.
    Linked 1-to-1 with User (user_id is unique).
    """

    __tablename__ = "employees"

    # ── Primary key ───────────────────────────────────────────────────────────
    id = db.Column(db.Integer, primary_key=True)

    # ── Foreign key → users ───────────────────────────────────────────────────
    # unique=True enforces the one-to-one relationship at DB level
    # ondelete="CASCADE" → deleting the User row also deletes this Employee row
    user_id = db.Column(
        db.Integer,
        db.ForeignKey("users.id", ondelete="CASCADE"),
        nullable=False,
        unique=True,
    )

    # ── Contact details (employee can edit these themselves) ──────────────────
    phone   = db.Column(db.String(20),  nullable=True)
    address = db.Column(db.Text,        nullable=True)

    # ── Job details (only admin can edit these) ───────────────────────────────
    job_title  = db.Column(db.String(100), nullable=True)
    department = db.Column(db.String(100), nullable=True)

    # ── Profile picture (employee can upload) ─────────────────────────────────
    # Stores the file path / URL — not the binary data
    profile_picture = db.Column(db.String(255), nullable=True)

    # ── Timestamps ────────────────────────────────────────────────────────────
    created_at = db.Column(db.DateTime, server_default=db.func.now())
    updated_at = db.Column(
        db.DateTime, server_default=db.func.now(), onupdate=db.func.now()
    )

    # ── Relationships ─────────────────────────────────────────────────────────
    # Back to the owning User record
    user = db.relationship("User", back_populates="employee")

    # One Employee → many Attendance rows
    attendances = db.relationship(
        "Attendance",
        back_populates="employee",
        cascade="all, delete-orphan",
    )

    # One Employee → many LeaveRequest rows
    leave_requests = db.relationship(
        "LeaveRequest",
        back_populates="employee",
        cascade="all, delete-orphan",
    )

    # One Employee → many Payroll rows (one per month/year)
    payrolls = db.relationship(
        "Payroll",
        back_populates="employee",
        cascade="all, delete-orphan",
    )

    # ── Helper methods ────────────────────────────────────────────────────────

    def to_dict(self):
        """
        Returns a dictionary with both employee profile fields
        and the linked user's name/email/role.
        Used by the employee profile API response.
        """
        return {
            "id":              self.id,
            "user_id":         self.user_id,
            "name":            self.user.name  if self.user else None,
            "email":           self.user.email if self.user else None,
            "role":            self.user.role  if self.user else None,
            "phone":           self.phone,
            "address":         self.address,
            "job_title":       self.job_title,
            "department":      self.department,
            "profile_picture": self.profile_picture,
            "created_at":      self.created_at.isoformat() if self.created_at else None,
        }

    def __repr__(self):
        return f"<Employee id={self.id} user_id={self.user_id}>"
