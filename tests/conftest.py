import re

import pytest

from textshare import create_app

TOKEN_RE = re.compile(r'name="csrf_token" value="([^"]+)"')


@pytest.fixture
def app(tmp_path):
    return create_app(
        {"TESTING": True, "SECRET_KEY": "test", "DATABASE": str(tmp_path / "test.db")}
    )


class Client:
    """Test client that fetches a real CSRF token from the site before each POST."""

    def __init__(self, app):
        self.app = app
        self.http = app.test_client()

    def get(self, url, **kwargs):
        return self.http.get(url, **kwargs)

    def post(self, url, data=None, csrf=True, **kwargs):
        data = dict(data or {})
        if csrf:
            with self.http.session_transaction() as session:
                token = session.get("csrf_token")
            for form_page in ("/login", "/"):  # the login form or, once logged in, the logout form
                if token is None:
                    match = TOKEN_RE.search(self.http.get(form_page).get_data(as_text=True))
                    token = match.group(1) if match else None
            data["csrf_token"] = token
        return self.http.post(url, data=data, **kwargs)

    def register(self, username="alice", password="password123"):
        return self.post(
            "/register",
            {"username": username, "password": password, "confirm_password": password},
        )

    def login(self, username="alice", password="password123"):
        return self.post("/login", {"username": username, "password": password})

    def logout(self):
        return self.post("/logout")

    def new_post(self, title="Hello", body="World", category_id=1):
        return self.post("/posts/new", {"title": title, "body": body, "category_id": category_id})


@pytest.fixture
def client(app):
    return Client(app)


@pytest.fixture
def alice(client):
    client.register("alice")
    return client


@pytest.fixture
def bob(app):
    other = Client(app)
    other.register("bob")
    return other


def text(response):
    return response.get_data(as_text=True)
