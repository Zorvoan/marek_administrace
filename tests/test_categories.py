import re

from conftest import text


def test_default_categories_exist(client):
    page = text(client.get("/categories"))
    for name in ["General", "Technology", "Life", "Questions"]:
        assert name in page
    assert "to create a category" in page  # anonymous users are told to log in


def test_create_category(alice):
    response = alice.post("/categories", {"name": "  Board   games "})
    assert response.status_code == 302
    page = text(alice.get("/categories"))
    assert "Board games" in page and "Category “Board games” created" in page

    # It is immediately usable in the new post form and the search dropdown.
    assert "Board games" in text(alice.get("/posts/new"))
    assert "Board games" in text(alice.get("/"))


def test_category_names_are_unique_case_insensitively(alice):
    assert "already exists" in text(alice.post("/categories", {"name": "TECHNOLOGY"}, follow_redirects=True))


def test_category_name_validation(alice):
    for name in ["", "   ", "x" * 41]:
        assert "must be 1-40" in text(alice.post("/categories", {"name": name}, follow_redirects=True))
    assert "x" * 40 in text(
        alice.post("/categories", {"name": "x" * 40}, follow_redirects=True)
    )


def test_category_names_are_escaped(alice):
    alice.post("/categories", {"name": "<i>x</i>"})
    page = text(alice.get("/categories"))
    assert "<i>x</i>" not in page and "&lt;i&gt;x&lt;/i&gt;" in page


def test_category_counts(alice):
    alice.new_post(category_id=2)
    alice.new_post(category_id=2)
    page = text(alice.get("/categories"))
    assert re.search(r"Technology</a>\s*<span[^>]*>2 posts</span>", page)
    assert re.search(r"Life</a>\s*<span[^>]*>0 posts</span>", page)


def test_search_dropdown_reflects_selected_category(client):
    page = text(client.get("/?category=2&q=hello"))
    assert re.search(r'<option value="2" selected>Technology</option>', page)
    assert 'value="hello"' in page


def test_navbar_form_is_blank_outside_search_and_escapes_input(client):
    page = text(client.get("/login?q=abc&category=2"))
    assert 'value="abc"' not in page and "selected" not in page

    page = text(client.get("/", query_string={"q": '"><script>alert(1)</script>'}))
    assert "<script>alert(1)</script>" not in page
    assert "&#34;&gt;&lt;script&gt;" in page


def test_navbar_uses_the_validated_category(client):
    assert '<option value="2" selected>' in text(client.get("/?category=02"))
    assert " selected>" not in text(client.get("/?category=999"))
