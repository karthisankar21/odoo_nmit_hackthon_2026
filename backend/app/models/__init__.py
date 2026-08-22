# =============================================================================
# models/__init__.py
# -----------------------------------------------------------------------------
# Central import point for all SQLAlchemy models.
# Flask-Migrate scans this module to discover all tables.
# Import order matters: User before Employee (FK dependency),
# Employee before Attendance/LeaveRequest/Payroll.
# =============================================================================

from .user         import User          # noqa: F401
from .employee     import Employee      # noqa: F401
from .attendance   import Attendance    # noqa: F401
from .leave_request import LeaveRequest # noqa: F401
from ..blueprints.payroll      import Payroll       # noqa: F401
