import os
import secrets
from pathlib import Path

from flask import Flask, render_template
from werkzeug.exceptions import HTTPException

from . import auth, categories, db, posts, security


def load_secret_key(instance_path):
    """Use $SECRET_KEY if set, otherwise a random key persisted in the instance folder."""
    if os.environ.get("SECRET_KEY"):
        return os.environ["SECRET_KEY"]
    path = Path(instance_path) / "secret_key"
    if not path.exists():
        path.parent.mkdir(parents=True, exist_ok=True)
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w") as f:
            f.write(secrets.token_hex(32))
    return path.read_text().strip()


def create_app(test_config=None):
    app = Flask(__name__)
    app.config.from_mapping(
        DATABASE=os.path.join(app.instance_path, "textshare.db"),
        MAX_CONTENT_LENGTH=256 * 1024,
        SESSION_COOKIE_SAMESITE="Lax",
        # Set SESSION_COOKIE_SECURE=1 when serving over HTTPS.
        SESSION_COOKIE_SECURE=os.environ.get("SESSION_COOKIE_SECURE") == "1",
    )
    if test_config:
        app.config.update(test_config)
    if not app.config.get("SECRET_KEY"):
        app.config["SECRET_KEY"] = load_secret_key(app.instance_path)

    db.init_app(app)
    security.init_app(app)
    for module in (auth, posts, categories):
        app.register_blueprint(module.bp)

    @app.errorhandler(HTTPException)
    def show_error(error):
        return render_template("error.html", error=error), error.code

    return app
