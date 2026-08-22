# =============================================================================
# utils/auth_helpers.py
# -----------------------------------------------------------------------------
# Shared authentication utilities used by all blueprints.
#
# Contents:
#   1. require_role(*roles)  — decorator that enforces JWT role-based access
#
# How it works:
#   Every protected route uses @jwt_required() from flask_jwt_extended FIRST,
#   then @require_role(...) checks the 'role' claim inside the token.
#   If the role does not match, a 403 Forbidden is returned immediately.
# =============================================================================

import logging
from functools import wraps
from flask import jsonify
from flask_jwt_extended import get_jwt

# Re-use the single shared logger from app.py
logger = logging.getLogger("dayflow")


# =============================================================================
# DECORATOR: require_role
# =============================================================================

def require_role(*allowed_roles):
    """
    Route decorator that enforces role-based access control.

    Usage:
        @jwt_required()
        @require_role("admin")
        def admin_only_route():
            ...

        @jwt_required()
        @require_role("employee", "admin")
        def both_roles_allowed():
            ...

    How it works:
        1. Reads the JWT claims (already validated by @jwt_required())
        2. Extracts the 'role' claim from the token payload
        3. Checks if the role is in the allowed_roles list
        4. Returns 403 if not; calls the route function if yes

    Args:
        *allowed_roles: one or more role strings e.g. "admin", "employee"

    Returns:
        The decorated function or a 403 JSON response.
    """
    def decorator(fn):
        @wraps(fn)
        def wrapper(*args, **kwargs):

            # ── Extract JWT claims ────────────────────────────────────────────
            # get_jwt() returns the full decoded token payload as a dict.
            # Our login endpoint stores 'role' as an additional claim.
            claims = get_jwt()
            actual_role = claims.get("role", "")

            # ── Check role ────────────────────────────────────────────────────
            if actual_role not in allowed_roles:
                logger.warning(
                    "Access denied — required roles: %s, actual role: %s, user_id: %s",
                    allowed_roles,
                    actual_role,
                    claims.get("sub", "unknown"),
                )
                return jsonify({"error": "Access denied — insufficient permissions"}), 403

            # ── Role OK — proceed to route handler ────────────────────────────
            logger.debug(
                "Role check passed — role: %s, user_id: %s, allowed: %s",
                actual_role,
                claims.get("sub", "unknown"),
                allowed_roles,
            )
            return fn(*args, **kwargs)

        return wrapper
    return decorator
