import sqlite3
from pathlib import Path

from flask import current_app, g


def get_db():
    """Return this request's SQLite connection, opening it on first use."""
    if "db" not in g:
        db = sqlite3.connect(current_app.config["DATABASE"])
        db.row_factory = sqlite3.Row
        db.execute("PRAGMA foreign_keys = ON")
        # SQLite's own lower()/LIKE only fold ASCII; this makes search Unicode-aware.
        db.create_function("casefold", 1, str.casefold, deterministic=True)
        g.db = db
    return g.db


def close_db(error=None):
    db = g.pop("db", None)
    if db is not None:
        db.close()


def init_app(app):
    app.teardown_appcontext(close_db)
    Path(app.config["DATABASE"]).parent.mkdir(parents=True, exist_ok=True)
    with app.app_context():
        get_db().executescript(Path(__file__).with_name("schema.sql").read_text())
