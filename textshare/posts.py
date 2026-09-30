from flask import Blueprint, abort, flash, g, redirect, render_template, request, url_for

from .auth import login_required
from .categories import list_categories
from .db import get_db

bp = Blueprint("posts", __name__)

PER_PAGE = 10
TITLE_MAX = 120
BODY_MAX = 10_000
QUERY_MAX = 100
SQLITE_MAX_INT = 2**63 - 1

POST_SELECT = """
    SELECT p.id, p.user_id, p.category_id, p.title, p.body, p.created_at, p.updated_at,
           u.username, c.name AS category_name
    FROM posts p
    JOIN users u ON u.id = p.user_id
    JOIN categories c ON c.id = p.category_id
"""


def get_post(post_id):
    post = None
    if post_id <= SQLITE_MAX_INT:  # larger ids can't exist and would overflow the query
        post = get_db().execute(POST_SELECT + " WHERE p.id = ?", (post_id,)).fetchone()
    if post is None:
        abort(404)
    return post


def get_own_post(post_id):
    post = get_post(post_id)
    if post["user_id"] != g.user["id"]:
        abort(403)
    return post


def read_post_form():
    """Validate the submitted post form; return (cleaned values, list of errors)."""
    title = request.form.get("title", "").strip()
    body = request.form.get("body", "").replace("\r\n", "\n").replace("\r", "\n").strip()
    category_id = request.form.get("category_id", type=int)
    errors = []
    if not 1 <= len(title) <= TITLE_MAX:
        errors.append(f"Title must be 1-{TITLE_MAX} characters long.")
    if not 1 <= len(body) <= BODY_MAX:
        errors.append(f"Text must be 1-{BODY_MAX:,} characters long.")
    if category_id not in {c["id"] for c in list_categories()}:
        errors.append("Please choose a category.")
    return {"title": title, "body": body, "category_id": category_id}, errors


@bp.get("/")
def index():
    q = request.args.get("q", "").strip()[:QUERY_MAX]
    category_id = request.args.get("category", type=int)
    category = next((c for c in list_categories() if c["id"] == category_id), None)

    # Only fixed SQL fragments are joined below; all user input goes through `params`.
    conditions, params = [], []
    if category:
        conditions.append("p.category_id = ?")
        params.append(category["id"])
    for term in q.casefold().split():  # every word must appear in the title or the text
        conditions.append("(instr(casefold(p.title), ?) > 0 OR instr(casefold(p.body), ?) > 0)")
        params += [term, term]
    where = "WHERE " + " AND ".join(conditions) if conditions else ""

    db = get_db()
    total = db.execute(f"SELECT COUNT(*) FROM posts p {where}", params).fetchone()[0]
    pages = max(1, -(-total // PER_PAGE))
    page = min(max(request.args.get("page", 1, type=int), 1), pages)
    posts = db.execute(
        f"{POST_SELECT} {where} ORDER BY p.id DESC LIMIT ? OFFSET ?",
        [*params, PER_PAGE, (page - 1) * PER_PAGE],
    ).fetchall()

    return render_template(
        "posts/index.html",
        posts=posts,
        total=total,
        page=page,
        pages=pages,
        q=q,
        category=category,
        filters={"q": q or None, "category": category["id"] if category else None},
    )


@bp.get("/posts/<int:post_id>")
def detail(post_id):
    return render_template("posts/detail.html", post=get_post(post_id))


@bp.route("/posts/new", methods=("GET", "POST"))
@login_required
def create():
    form = {"title": "", "body": "", "category_id": None}
    if request.method == "POST":
        form, errors = read_post_form()
        if not errors:
            db = get_db()
            cursor = db.execute(
                "INSERT INTO posts (user_id, category_id, title, body) VALUES (?, ?, ?, ?)",
                (g.user["id"], form["category_id"], form["title"], form["body"]),
            )
            db.commit()
            flash("Post published.", "success")
            return redirect(url_for("posts.detail", post_id=cursor.lastrowid))
        for error in errors:
            flash(error, "error")
    return render_template(
        "posts/form.html", form=form, categories=list_categories(), post=None,
        title_max=TITLE_MAX, body_max=BODY_MAX,
    )


@bp.route("/posts/<int:post_id>/edit", methods=("GET", "POST"))
@login_required
def edit(post_id):
    post = get_own_post(post_id)
    form = post
    if request.method == "POST":
        form, errors = read_post_form()
        if not errors:
            db = get_db()
            db.execute(
                """
                UPDATE posts
                SET category_id = ?, title = ?, body = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
                """,
                (form["category_id"], form["title"], form["body"], post["id"], g.user["id"]),
            )
            db.commit()
            flash("Post updated.", "success")
            return redirect(url_for("posts.detail", post_id=post["id"]))
        for error in errors:
            flash(error, "error")
    return render_template(
        "posts/form.html", form=form, categories=list_categories(), post=post,
        title_max=TITLE_MAX, body_max=BODY_MAX,
    )


@bp.post("/posts/<int:post_id>/delete")
@login_required
def delete(post_id):
    post = get_own_post(post_id)
    db = get_db()
    db.execute("DELETE FROM posts WHERE id = ? AND user_id = ?", (post["id"], g.user["id"]))
    db.commit()
    flash("Post deleted.", "success")
    return redirect(url_for("posts.index"))
