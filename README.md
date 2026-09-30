# TextShare

A small, server-side rendered web app for sharing text posts, built with **Laravel 13**, **Blade** and **SQLite**. No JavaScript framework and no front-end build step.

## Features

- Sign up, log in and log out (bcrypt-hashed passwords, "remember me")
- Text posts with a title, a body and a category
- Categories: a few are seeded and any logged-in user can add more
- Search bar in the navigation bar: every word must appear in the title or text, optionally narrowed to one category (the same filters are reachable by clicking a category badge or a name on the *Categories* page)
- Only the author can edit or delete a post; everyone else gets `403` and never sees the buttons
- Pagination (10 posts per page, the search/category is kept across pages), responsive layout, light/dark theme

## Requirements

PHP 8.3+ with the `sqlite3`/`pdo_sqlite`, `mbstring`, `xml`, `dom`, `tokenizer` and `ctype` extensions, and [Composer](https://getcomposer.org).

## Run it

```bash
composer setup      # install, create .env + app key + SQLite file, migrate, seed categories
composer dev        # http://127.0.0.1:8000
```

`composer setup` is shorthand for `composer install`, copying `.env.example` to `.env`, `php artisan key:generate`, creating `database/database.sqlite` and `php artisan migrate --seed`.

## Tests

```bash
composer test
```

The suite (`tests/Feature`) covers sign up / login / logout, post CRUD, validation, search, the category filter, pagination, and that only authors can edit or delete their posts.

## How it is organised

```
routes/web.php                          every route, in one short file
app/Http/Controllers/PostController.php list + search + category filter, create, show, edit, delete
app/Http/Controllers/CategoryController.php
app/Http/Controllers/Auth/              RegisterController, SessionController (login/logout)
app/Http/Requests/                      RegisterRequest, PostRequest (validation rules)
app/Models/                             User, Post, Category (+ the Post::search() scope)
app/Policies/PostPolicy.php             who may edit/delete a post
app/View/Components/Layout.php          page shell: navigation bar + search form
resources/views/                        Blade templates (components/layout, posts/, categories/, auth/, errors/)
public/css/app.css                      the only stylesheet
```

## Design notes

- **Authorization** is enforced on the server by `PostPolicy` (`$user->id === $post->user_id`), wired in as route middleware in `PostController` (`can:update,post` / `can:delete,post`). Guests are redirected to the login page, other users get `403`. The post's author is always the logged-in user; it can't be set from the form.
- **CSRF**: every `POST`/`PUT`/`DELETE` form carries a token (Laravel's `VerifyCsrfToken` middleware), including log out.
- **XSS**: all output is escaped by Blade (`{{ }}`); line breaks in post text are preserved with CSS (`white-space: pre-wrap`), not by emitting HTML.
- **SQL injection**: Eloquent/query builder with bound parameters. In search, `%` and `_` typed by the user are matched literally.
- **Sessions**: the session id is regenerated on login and sign up and invalidated on logout. Login and sign up are throttled to 10 attempts per minute per IP.
- **Emails** are stored lowercase, so `Jane@x.com` and `jane@x.com` are the same account.
- **Development safety net**: outside production, Eloquent is in strict mode (lazy loading, mass-assignment mistakes and missing attributes throw), so N+1 queries fail the tests instead of shipping.

## Production checklist

Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, and serve `public/` over HTTPS with `SESSION_SECURE_COOKIE=true`. Point `DB_*` at a real database if you don't want SQLite, then run `php artisan migrate --force` and `php artisan config:cache route:cache view:cache`.

## Known limitations

- **Search case-folding depends on the database.** SQLite's `LIKE` ignores case for `A–Z` only, so on the default SQLite database a search for `řeka` will not find `Řeka` (searching `Řeka` does). MySQL is case-insensitive for all letters. PostgreSQL's `LIKE` is case-sensitive. It has only been tested on SQLite.
- Category names are unique according to the database's collation (case-insensitive on MySQL, case-sensitive on SQLite, so `Tech` and `tech` can coexist there).
- Search scans the posts table, which is fine for thousands of posts; for much larger data sets move to full-text search.
- There is no e-mail verification, password reset, or moderation/admin role, and categories can't be renamed or deleted.
