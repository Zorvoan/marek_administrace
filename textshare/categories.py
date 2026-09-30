import sqlite3

from flask import Blueprint, flash, redirect, render_template, request, url_for

from .auth import login_required
from .db import get_db

bp = Blueprint("categories", __name__)

NAME_MAX = 40


def list_categories():
    return get_db().execute(
        "SELECT id, name FROM categories ORDER BY name COLLATE NOCASE"
    ).fetchall()


@bp.app_context_processor
def inject_nav_categories():
    """Make the category list available to the search bar in the navbar."""
    return {"nav_categories": list_categories()}


@bp.get("/categories")
def index():
    categories = get_db().execute(
        """
        SELECT c.id, c.name, COUNT(p.id) AS post_count
        FROM categories c LEFT JOIN posts p ON p.category_id = c.id
        GROUP BY c.id
        ORDER BY c.name COLLATE NOCASE
        """
    ).fetchall()
    return render_template("categories/index.html", categories=categories, name_max=NAME_MAX)


@bp.post("/categories")
@login_required
def create():
    name = " ".join(request.form.get("name", "").split())  # trim and collapse whitespace
    if not name or len(name) > NAME_MAX:
        flash(f"Category name must be 1-{NAME_MAX} characters long.", "error")
        return redirect(url_for("categories.index"))
    db = get_db()
    try:
        db.execute("INSERT INTO categories (name) VALUES (?)", (name,))
        db.commit()
    except sqlite3.IntegrityError:
        flash("That category already exists.", "error")
    else:
        flash(f"Category “{name}” created.", "success")
    return redirect(url_for("categories.index"))
