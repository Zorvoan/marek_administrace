import re

from conftest import TOKEN_RE, text


def test_register_logs_in_and_navbar_shows_user(client):
    response = client.register("alice")
    assert response.status_code == 302
    page = text(client.get("/"))
    assert "alice" in page and "Log out" in page and "Log in" not in page


def test_register_validation(client):
    for username, password, confirm, message in [
        ("ab", "password123", "password123", "Username must be"),
        ("bad name!", "password123", "password123", "Username must be"),
        ("üser", "password123", "password123", "Username must be"),
        ("alice", "short", "short", "Password must be"),
        ("alice", "password123", "different123", "Passwords do not match"),
    ]:
        response = client.post(
            "/register",
            {"username": username, "password": password, "confirm_password": confirm},
        )
        assert response.status_code == 200
        assert message in text(response)


def test_username_is_unique_case_insensitively(client, app):
    client.register("Alice")
    client.logout()
    response = client.register("alice")
    assert "already taken" in text(response)


def test_password_is_hashed(client, app):
    client.register("alice", "password123")
    from textshare.db import get_db

    with app.app_context():
        stored = get_db().execute("SELECT password_hash FROM users").fetchone()[0]
    assert "password123" not in stored


def test_login_and_logout(client):
    client.register("alice")
    client.logout()
    assert "Log in" in text(client.get("/"))

    assert "Invalid username or password" in text(client.login("alice", "wrong-password"))
    assert "Invalid username or password" in text(client.login("nobody", "password123"))
    assert client.login("ALICE", "password123").status_code == 302  # case-insensitive
    assert "Log out" in text(client.get("/"))

    client.logout()
    assert "Log in" in text(client.get("/"))


def test_logout_requires_post(client):
    assert client.get("/logout").status_code == 405


def test_login_rotates_session(client):
    client.register("alice")
    client.logout()
    before = client.get("/login")
    old_token = TOKEN_RE.search(text(before)).group(1)
    client.login()
    with client.http.session_transaction() as session:
        assert session.get("csrf_token") != old_token


def test_protected_pages_redirect_to_login_with_next(client):
    response = client.get("/posts/new")
    assert response.status_code == 302
    assert response.headers["Location"] == "/login?next=/posts/new"


def test_login_follows_safe_next(client):
    client.register("alice")
    client.logout()
    response = client.post(
        "/login", {"username": "alice", "password": "password123", "next": "/posts/new"}
    )
    assert response.headers["Location"] == "/posts/new"


def test_login_ignores_unsafe_next(client):
    client.register("alice")
    for bad in ["https://evil.example", "//evil.example", "/\\evil.example", "/\t/evil.example", "evil"]:
        client.logout()
        response = client.post(
            "/login", {"username": "alice", "password": "password123", "next": bad}
        )
        assert response.headers["Location"] == "/", bad


def test_logged_in_users_skip_login_and_register(alice):
    assert alice.get("/login").status_code == 302
    assert alice.get("/register").status_code == 302


def test_csrf_is_enforced(client):
    response = client.post("/register", {"username": "alice"}, csrf=False)
    assert response.status_code == 400
    response = client.post("/register", {"csrf_token": ""}, csrf=False)  # empty must not pass
    assert response.status_code == 400
    response = client.post("/register", {"csrf_token": "é"}, csrf=False)  # non-ASCII must not crash
    assert response.status_code == 400
    client.get("/login")  # now the session has a token, but a wrong one is still rejected
    response = client.post("/register", {"csrf_token": "wrong"}, csrf=False)
    assert response.status_code == 400


def test_security_headers(client):
    response = client.get("/")
    assert "default-src 'self'" in response.headers["Content-Security-Policy"]
    assert response.headers["X-Content-Type-Options"] == "nosniff"
    assert response.headers["X-Frame-Options"] == "DENY"


def test_unknown_page_renders_error_template(client):
    response = client.get("/nope")
    assert response.status_code == 404
    assert "Not Found" in text(response)
