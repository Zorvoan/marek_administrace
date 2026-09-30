import hmac
import secrets

from flask import abort, request, session
from markupsafe import Markup

CSP = "default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'"


def csrf_token():
    if "csrf_token" not in session:
        session["csrf_token"] = secrets.token_urlsafe(32)
    return session["csrf_token"]


def csrf_field():
    return Markup('<input type="hidden" name="csrf_token" value="{}">').format(csrf_token())


def check_csrf():
    """Reject state-changing requests that don't carry the session's CSRF token."""
    if request.method in ("POST", "PUT", "PATCH", "DELETE"):
        expected = session.get("csrf_token", "")
        sent = request.form.get("csrf_token", "")
        # compare_digest needs bytes: with str it raises on non-ASCII input.
        if not expected or not hmac.compare_digest(sent.encode(), expected.encode()):
            abort(400, "Invalid or missing CSRF token. Reload the page and try again.")


def set_security_headers(response):
    response.headers["Content-Security-Policy"] = CSP
    response.headers["X-Content-Type-Options"] = "nosniff"
    response.headers["X-Frame-Options"] = "DENY"
    response.headers["Referrer-Policy"] = "same-origin"
    return response


def init_app(app):
    app.before_request(check_csrf)
    app.after_request(set_security_headers)
    app.add_template_global(csrf_field)
