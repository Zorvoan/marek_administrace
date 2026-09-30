# TextShare

A small server-side rendered web app for sharing text posts, built with **Flask**, **Jinja2** and **SQLite**.

## Features

- Sign up, log in and log out (passwords are hashed; sessions are signed cookies)
- Text posts with a title, a body and a category
- Categories: a few are pre-created and any logged-in user can add more
- Search bar in the navbar: type words (all must match the title or text, case-insensitive, Unicode-aware) and/or pick a category
- Only the author can edit or delete a post; everyone else gets `403` (and never sees the buttons)
- Pagination (10 posts per page), responsive layout, light/dark theme

## Run it

```bash
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt

flask --app textshare run --debug
```

Open <http://127.0.0.1:5000>. The SQLite database and tables are created automatically in `instance/`.

## Configuration

| Environment variable    | Purpose                                                                                       |
| ----------------------- | --------------------------------------------------------------------------------------------- |
| `SECRET_KEY`            | Signs session cookies. If unset, a random key is generated once and stored in `instance/secret_key`. Set it explicitly in production. |
| `SESSION_COOKIE_SECURE` | Set to `1` when serving over HTTPS so the session cookie is never sent over plain HTTP.       |

For production use a real WSGI server, e.g. `pip install gunicorn && gunicorn "textshare:create_app()"`, behind HTTPS.

## Tests

```bash
pip install -r requirements-dev.txt
pytest
```

## Layout

```
textshare/
  __init__.py     app factory, config, error pages
  db.py           SQLite connection handling (plain sqlite3, no ORM)
  schema.sql      tables + default categories (applied on startup, idempotent)
  security.py     CSRF protection and security headers
  auth.py         sign up / log in / log out, login_required
  posts.py        list + search + category filter, create, view, edit, delete
  categories.py   list and create categories
  templates/      Jinja2 templates
  static/         one stylesheet, favicon (no JavaScript)
tests/            pytest suite
```

## Design notes

- **Authorization** is enforced on the server: `edit` and `delete` load the post, return `403` unless `post.user_id` is the logged-in user, and the `UPDATE`/`DELETE` statements also filter on `user_id`.
- **CSRF**: every `POST` (including log out) must carry the per-session token that all forms include.
- **Injection / XSS**: all SQL is parameterised (search uses `instr()`, so `%` and `_` are literal); Jinja auto-escapes all output.
- **Headers**: a strict Content-Security-Policy is sent. The app uses no inline scripts or styles, and the delete confirmation is a plain `<details>` element.
- **Usernames** are ASCII (`A-Z a-z 0-9 _`) and unique case-insensitively. Category names may be any text; uniqueness is case-insensitive for ASCII letters (an SQLite limitation).

## Known limitations

- No rate limiting on login/sign up; put the app behind a reverse proxy or add Flask-Limiter before exposing it publicly.
- No e-mail verification or password reset.
- Search scans the posts table, which is fine for thousands of posts; for much larger data sets switch to SQLite FTS5.
