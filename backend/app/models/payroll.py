# =============================================================================
# models/payroll.py
# -----------------------------------------------------------------------------
# Stores monthly salary records for each employee.
# One record per employee per month/year combination.
# net_salary is ALWAYS computed server-side: basic + allowances - deductions.
# Employees can only read their own payroll.
# Admin can create/update salary structures for any employee.
# =============================================================================

from extensions import db


class Payroll(db.Model):
    """
    Table: payroll
    One row per employee per month/year.
    Unique constraint on (employee_id, month, year) prevents duplicates.

    net_salary formula (enforced in blueprint, not DB):
        net_salary = basic_salary + allowances - deductions
    """

    __tablename__ = "payroll"

    # ── Primary key ───────────────────────────────────────────────────────────
    id = db.Column(db.Integer, primary_key=True)

    # ── Foreign key → employees ───────────────────────────────────────────────
    employee_id = db.Column(
        db.Integer,
        db.ForeignKey("employees.id", ondelete="CASCADE"),
        nullable=False,
    )

    # ── Salary components ─────────────────────────────────────────────────────
    # Numeric(12, 2) → up to 9,999,999,999.99 with 2 decimal places
    basic_salary = db.Column(db.Numeric(12, 2), nullable=False, default=0)
    allowances   = db.Column(db.Numeric(12, 2), nullable=False, default=0)
    deductions   = db.Column(db.Numeric(12, 2), nullable=False, default=0)

    # net_salary is always written by the API — never trusted from the client
    net_salary = db.Column(db.Numeric(12, 2), nullable=False, default=0)

    # ── Pay period ────────────────────────────────────────────────────────────
    month = db.Column(db.Integer, nullable=False)   # 1 = January … 12 = December
    year  = db.Column(db.Integer, nullable=False)   # e.g. 2026

    # ── Timestamps ────────────────────────────────────────────────────────────
    created_at = db.Column(db.DateTime, server_default=db.func.now())
    updated_at = db.Column(
        db.DateTime, server_default=db.func.now(), onupdate=db.func.now()
    )

    # ── Table-level constraints ───────────────────────────────────────────────
    # One payroll record per employee per month/year — enforced at DB level
    __table_args__ = (
        db.UniqueConstraint(
            "employee_id", "month", "year",
            name="uq_payroll_employee_month_year"
        ),
    )

    # ── Relationships ─────────────────────────────────────────────────────────
    employee = db.relationship("Employee", back_populates="payrolls")

    # ── Helper methods ────────────────────────────────────────────────────────

    def compute_net(self):
        """
        Recomputes net_salary from components and stores the result.
        Call this before every db.session.commit() on a Payroll record.
        Formula: net = basic + allowances - deductions
        """
        self.net_salary = self.basic_salary + self.allowances - self.deductions

    def to_dict(self):
        """
        Serialises the payroll record to a dict for API responses.
        All Numeric values are cast to float for JSON serialisation.
        """
        return {
            "id":           self.id,
            "employee_id":  self.employee_id,
            "employee_name": self.employee.user.name if self.employee and self.employee.user else None,
            "basic_salary": float(self.basic_salary),
            "allowances":   float(self.allowances),
            "deductions":   float(self.deductions),
            "net_salary":   float(self.net_salary),
            "month":        self.month,
            "year":         self.year,
            "updated_at":   self.updated_at.isoformat() if self.updated_at else None,
        }

    def __repr__(self):
        return (
            f"<Payroll employee_id={self.employee_id} "
            f"{self.month}/{self.year} net={self.net_salary}>"
        )
