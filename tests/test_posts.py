import re

from conftest import Client, text
from textshare.db import get_db


def post_id_from(response):
    return int(re.search(r"/posts/(\d+)$", response.headers["Location"]).group(1))


def test_create_and_view_post(alice):
    response = alice.new_post("My title", "Line one\nLine two", category_id=2)
    assert response.status_code == 302
    page = text(alice.get(response.headers["Location"]))
    assert "My title" in page and "Line one\nLine two" in page
    assert "by alice" in page and "Technology" in page
    assert "Edit" in page and "Delete" in page  # the author sees the actions


def test_post_content_is_escaped(alice):
    response = alice.new_post("<script>alert(1)</script>", "<b>bold</b> & more")
    page = text(alice.get(response.headers["Location"]))
    assert "<script>alert(1)</script>" not in page
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in page
    assert "&lt;b&gt;bold&lt;/b&gt; &amp; more" in page


def test_post_form_validation(alice):
    for kwargs, message in [
        ({"title": "  "}, "Title must be"),
        ({"title": "x" * 121}, "Title must be"),
        ({"body": "   "}, "Text must be"),
        ({"body": "x" * 10_001}, "Text must be"),
        ({"category_id": 999}, "choose a category"),
        ({"category_id": 10**30}, "choose a category"),
        ({"category_id": "abc"}, "choose a category"),
        ({"category_id": ""}, "choose a category"),
    ]:
        response = alice.new_post(**kwargs)
        assert response.status_code == 200, kwargs
        assert message in text(response), kwargs
    assert "No posts found" in text(alice.get("/"))


def test_invalid_form_keeps_entered_values(alice):
    page = text(alice.new_post("Keep me", "", category_id=3))
    assert 'value="Keep me"' in page
    assert re.search(r'<option value="3" selected>', page)


def test_anonymous_cannot_write(client):
    for url in ["/posts/new", "/posts/1/edit"]:
        assert client.get(url).status_code == 302
    for url in ["/posts/new", "/posts/1/edit", "/posts/1/delete"]:
        assert client.post(url, {"title": "t", "body": "b", "category_id": 1}).status_code == 302
    assert client.post("/categories", {"name": "Sneaky"}).status_code == 302


def test_author_can_edit_and_delete(alice):
    post_id = post_id_from(alice.new_post("Old", "Old body"))

    page = text(alice.get(f"/posts/{post_id}/edit"))
    assert 'value="Old"' in page and ">Old body</textarea>" in page

    response = alice.post(
        f"/posts/{post_id}/edit", {"title": "New", "body": "New body", "category_id": 3}
    )
    assert response.status_code == 302
    page = text(alice.get(f"/posts/{post_id}"))
    assert "New body" in page and "Old body" not in page and "edited" in page and "Life" in page

    response = alice.post(f"/posts/{post_id}/delete")
    assert response.status_code == 302
    assert alice.get(f"/posts/{post_id}").status_code == 404


def test_edit_validation_errors_do_not_change_post(alice):
    post_id = post_id_from(alice.new_post("Keep", "Keep body"))
    response = alice.post(f"/posts/{post_id}/edit", {"title": "", "body": "x", "category_id": 1})
    assert "Title must be" in text(response)
    assert "Keep body" in text(alice.get(f"/posts/{post_id}"))


def test_other_users_cannot_edit_or_delete(alice, bob):
    post_id = post_id_from(alice.new_post("Alice post", "Alice body"))

    page = text(bob.get(f"/posts/{post_id}"))
    assert "Alice post" in page and "Edit" not in page and "Delete" not in page

    assert bob.get(f"/posts/{post_id}/edit").status_code == 403
    edit = bob.post(f"/posts/{post_id}/edit", {"title": "Hacked", "body": "Hacked", "category_id": 1})
    assert edit.status_code == 403
    assert bob.post(f"/posts/{post_id}/delete").status_code == 403
    assert bob.get(f"/posts/{post_id}/delete").status_code == 405

    page = text(alice.get(f"/posts/{post_id}"))
    assert "Alice body" in page and "Hacked" not in page


def test_anonymous_viewers_do_not_see_actions(alice, app):
    post_id = post_id_from(alice.new_post())
    page = text(Client(app).get(f"/posts/{post_id}"))
    assert "Edit" not in page and "Delete" not in page


def test_delete_requires_csrf(alice):
    post_id = post_id_from(alice.new_post())
    assert alice.post(f"/posts/{post_id}/delete", csrf=False).status_code == 400
    assert alice.get(f"/posts/{post_id}").status_code == 200


def test_missing_and_absurd_post_ids(alice):
    for post_id in [999, 0, 10**30]:
        assert alice.get(f"/posts/{post_id}").status_code == 404
        assert alice.get(f"/posts/{post_id}/edit").status_code == 404
        assert alice.post(f"/posts/{post_id}/delete").status_code == 404


def test_search_by_title_and_body_case_insensitive(alice):
    alice.new_post("Flask tips", "Use blueprints")
    alice.new_post("Cooking", "Boil the FLASK of water")
    alice.new_post("Unrelated", "Nothing here")

    page = text(alice.get("/?q=flask"))
    assert "Flask tips" in page and "Cooking" in page and "Unrelated" not in page
    assert "2 posts" in page

    page = text(alice.get("/?q=BLUEPRINTS"))
    assert "Flask tips" in page and "Cooking" not in page


def test_search_is_unicode_aware(alice):
    alice.new_post("Věda a technika", "Příliš žluťoučký kůň")
    for query in ["věda", "VĚDA", "žluťoučký", "KŮŇ"]:
        assert "Věda a technika" in text(alice.get("/", query_string={"q": query})), query


def test_search_requires_every_word(alice):
    alice.new_post("Python web", "flask app")
    alice.new_post("Python cli", "click app")
    page = text(alice.get("/?q=python flask"))
    assert "Python web" in page and "Python cli" not in page


def test_search_treats_wildcards_literally(alice):
    alice.new_post("100% sure", "body")
    alice.new_post("Something else", "body")
    for query, expected in [("%", "100% sure"), ("_", None), ("' OR 1=1 --", None)]:
        page = text(alice.get("/", query_string={"q": query}))
        if expected:
            assert expected in page and "Something else" not in page
        else:
            assert "No posts found" in page


def test_category_filter_and_combined_search(alice):
    alice.new_post("Tech one", "shared word", category_id=2)
    alice.new_post("Life one", "shared word", category_id=3)

    page = text(alice.get("/?category=2"))
    assert "Tech one" in page and "Life one" not in page

    page = text(alice.get("/?category=3&q=shared"))
    assert "Life one" in page and "Tech one" not in page

    page = text(alice.get("/?category=3&q=missing"))
    assert "No posts found" in page

    page = text(alice.get("/"))
    assert "Tech one" in page and "Life one" in page


def test_bad_query_parameters_do_not_crash(client):
    for query in ["category=abc", "category=999", f"category={10**30}", "page=abc", "page=-3",
                  f"page={10**30}", "q=" + "x" * 5000, "q=%00"]:
        assert client.get("/?" + query).status_code == 200, query


def test_pagination(alice):
    for i in range(25):
        alice.new_post(f"Post number {i:02d}", "body")
    page1 = text(alice.get("/"))
    assert "Post number 24" in page1 and "Post number 15" in page1 and "Post number 14" not in page1
    assert "Page 1 of 3" in page1 and "Older" in page1 and "Newer" not in page1

    page3 = text(alice.get("/?page=3"))
    assert "Post number 04" in page3 and "Post number 05" not in page3
    assert "Newer" in page3 and "Older" not in page3
    assert "Page 3 of 3" in text(alice.get("/?page=99"))  # clamped
    assert "Page 1 of 3" in text(alice.get("/?page=0"))

    # Pagination links keep the active search and category.
    alice.new_post("Extra flask post", "body", category_id=2)
    links = re.findall(r'href="(/\?[^"]*)"', text(alice.get("/?category=1&page=1")))
    assert any("category=1" in link and "page=2" in link for link in links)


def test_posts_show_on_index_newest_first(alice):
    alice.new_post("First", "body")
    alice.new_post("Second", "body")
    page = text(alice.get("/"))
    assert page.index("Second") < page.index("First")


def test_deleting_the_last_post_is_reflected_in_lists(alice):
    post_id = post_id_from(alice.new_post("Temp", "body"))
    alice.post(f"/posts/{post_id}/delete")
    assert "Temp" not in text(alice.get("/"))


def test_foreign_keys_are_enforced(app):
    import sqlite3

    with app.app_context():
        db = get_db()
        try:
            db.execute("INSERT INTO posts (user_id, category_id, title, body) VALUES (99, 99, 't', 'b')")
        except sqlite3.IntegrityError:
            return
    raise AssertionError("foreign keys are not enforced")
