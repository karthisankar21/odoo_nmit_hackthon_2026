# =============================================================================
# models/user.py
# -----------------------------------------------------------------------------
# Represents a system user (employee or admin).
# Every person who can log in has one User record.
# The User holds credentials (password_hash) and role.
# Detailed HR profile data lives in the linked Employee record.
# =============================================================================

from extensions import db


class User(db.Model):
    """
    Table: users
    One row per person who can log in to Dayflow.
    role = 'employee' → regular staff
    role = 'admin'    → HR officer / manager with approval rights
    """

    __tablename__ = "users"

    # ── Primary key ───────────────────────────────────────────────────────────
    id = db.Column(db.Integer, primary_key=True)

    # ── Identity fields ───────────────────────────────────────────────────────
    name  = db.Column(db.String(100), nullable=False)
    email = db.Column(db.String(150), unique=True, nullable=False)

    # ── Role ──────────────────────────────────────────────────────────────────
    # Allowed values: 'employee' | 'admin'
    # Default is 'employee' so accidental registrations don't get admin rights
    role = db.Column(db.String(20), nullable=False, default="employee")

    # ── Credentials ───────────────────────────────────────────────────────────
    # Raw passwords are NEVER stored — only the bcrypt hash
    password_hash = db.Column(db.String(255), nullable=False)

    # ── Account status ────────────────────────────────────────────────────────
    # is_active=False → account is disabled, login is blocked
    is_active = db.Column(db.Boolean, nullable=False, default=True)

    # ── Timestamps ────────────────────────────────────────────────────────────
    created_at = db.Column(db.DateTime, server_default=db.func.now())
    updated_at = db.Column(
        db.DateTime, server_default=db.func.now(), onupdate=db.func.now()
    )

    # ── Relationships ─────────────────────────────────────────────────────────
    # One User → one Employee profile (uselist=False = one-to-one)
    # cascade="all, delete-orphan" → deleting User also deletes Employee
    employee = db.relationship(
        "Employee",
        back_populates="user",
        uselist=False,
        cascade="all, delete-orphan",
    )

    # ── Helper methods ────────────────────────────────────────────────────────

    def is_admin(self):
        """Returns True if this user has admin/HR role."""
        return self.role == "admin"

    def to_dict(self):
        """
        Returns a safe dictionary representation of the user.
        password_hash is intentionally excluded.
        """
        return {
            "id":         self.id,
            "name":       self.name,
            "email":      self.email,
            "role":       self.role,
            "is_active":  self.is_active,
            "created_at": self.created_at.isoformat() if self.created_at else None,
        }

    def __repr__(self):
        return f"<User id={self.id} email={self.email} role={self.role}>"
