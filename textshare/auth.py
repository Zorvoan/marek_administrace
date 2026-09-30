import functools
import re
import sqlite3

from flask import Blueprint, flash, g, redirect, render_template, request, session, url_for
from werkzeug.security import check_password_hash, generate_password_hash

from .db import get_db

bp = Blueprint("auth", __name__)

USERNAME_RE = re.compile(r"[A-Za-z0-9_]{3,30}")
PASSWORD_MIN, PASSWORD_MAX = 8, 128
# Only same-site absolute paths may be used as a post-login redirect target.
SAFE_NEXT_RE = re.compile(r"/(?!/)[\w\-./?=&%+~]*")


@bp.before_app_request
def load_user():
    user_id = session.get("user_id")
    g.user = None
    if user_id is not None:
        g.user = get_db().execute(
            "SELECT id, username FROM users WHERE id = ?", (user_id,)
        ).fetchone()


def login_required(view):
    @functools.wraps(view)
    def wrapped(**kwargs):
        if g.user is None:
            flash("Please log in to continue.", "error")
            next_url = request.full_path.rstrip("?") if request.method == "GET" else None
            return redirect(url_for("auth.login", next=next_url))
        return view(**kwargs)

    return wrapped


def log_in(user_id):
    session.clear()  # drops any old session, including its CSRF token
    session["user_id"] = user_id


@bp.route("/register", methods=("GET", "POST"))
def register():
    if g.user:
        return redirect(url_for("posts.index"))
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        errors = []
        if not USERNAME_RE.fullmatch(username):
            errors.append("Username must be 3-30 characters: letters, numbers and underscores.")
        if not PASSWORD_MIN <= len(password) <= PASSWORD_MAX:
            errors.append(f"Password must be {PASSWORD_MIN}-{PASSWORD_MAX} characters long.")
        elif password != request.form.get("confirm_password", ""):
            errors.append("Passwords do not match.")
        if not errors:
            db = get_db()
            try:
                cursor = db.execute(
                    "INSERT INTO users (username, password_hash) VALUES (?, ?)",
                    (username, generate_password_hash(password)),
                )
                db.commit()
            except sqlite3.IntegrityError:
                errors.append("That username is already taken.")
            else:
                log_in(cursor.lastrowid)
                flash(f"Welcome, {username}!", "success")
                return redirect(url_for("posts.index"))
        for error in errors:
            flash(error, "error")
    return render_template("auth/register.html")


@bp.route("/login", methods=("GET", "POST"))
def login():
    if g.user:
        return redirect(url_for("posts.index"))
    next_url = request.values.get("next", "")
    if request.method == "POST":
        user = get_db().execute(
            "SELECT id, password_hash FROM users WHERE username = ?",
            (request.form.get("username", "").strip(),),
        ).fetchone()
        password = request.form.get("password", "")
        if user is not None and check_password_hash(user["password_hash"], password):
            log_in(user["id"])
            return redirect(next_url if SAFE_NEXT_RE.fullmatch(next_url) else url_for("posts.index"))
        flash("Invalid username or password.", "error")
    return render_template("auth/login.html", next_url=next_url)


@bp.post("/logout")
def logout():
    session.clear()
    flash("You have been logged out.", "success")
    return redirect(url_for("posts.index"))
