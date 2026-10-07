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

PHP 8.3+ and [Composer](https://getcomposer.org). These PHP extensions must be enabled: `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `phar`, `session`, `tokenizer`, `xml`, `xmlwriter`, plus `pdo_sqlite` and `sqlite3` for the default SQLite database. Run `composer check-platform-reqs` to see what is missing.

**Windows:** the PHP zip ships with most extensions switched off. Copy `php.ini-development` to `php.ini` (in the PHP folder) if you have no `php.ini`, then remove the leading `;` from these lines and open a new terminal (`php --ini` shows which file is used):

```ini
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=curl
extension=zip
```

If `extension_dir` is commented out, also enable `extension_dir = "ext"`.

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
- **Sessions**: the session id is regenerated on login and sign up and invalidated on logout (an old cookie is useless afterwards). The cookie is `HttpOnly`, `SameSite=Lax`, and `Secure` automatically when `APP_URL` starts with `https://`.
- **Rate limits** (`AppServiceProvider`): login allows 5 tries a minute per email + IP (so someone guessing one account is stopped, but a class sharing one router is not) and 60 a minute per IP; sign up allows 10 a minute per IP.
- **Security headers** (`SecurityHeaders` middleware): `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'` (no clickjacking), `nosniff`, a `Referrer-Policy`, a strict `Content-Security-Policy` (`default-src 'self'`; the app has no inline scripts or styles, and the delete confirmation is a plain `<details>` element) and HSTS over HTTPS.
- **Trusted hosts**: outside `APP_ENV=local`, only the host in `APP_URL` (and its subdomains) is accepted, so a forged `Host` header can't poison generated links.
- **Emails** are stored lowercase, so `Jane@x.com` and `jane@x.com` are the same account.
- **Development safety net**: outside production, Eloquent is in strict mode (lazy loading, mass-assignment mistakes and missing attributes throw), so N+1 queries fail the tests instead of shipping.

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false` and an `APP_URL` that is your real **https** URL. That one setting also switches on trusted hosts, `Secure` cookies and HSTS; a wrong `APP_URL` makes the site answer `400`.
- `composer install --no-dev --optimize-autoloader`, then `php artisan migrate --force` and `php artisan config:cache route:cache view:cache`.
- Serve only the `public/` folder, over HTTPS. Never expose the project root (it contains `.env`).
- Behind a reverse proxy or load balancer, tell Laravel to trust it (`$middleware->trustProxies(at: '...')` in `bootstrap/app.php`); otherwise every visitor shares the proxy's IP address for rate limiting and the app can't tell the request was HTTPS.
- Set `expose_php = Off` in `php.ini` so responses don't announce the PHP version.
- Point `DB_*` at a real database if you don't want SQLite.

## Known limitations

- **Search case-folding depends on the database.** SQLite's `LIKE` ignores case for `A–Z` only, so on the default SQLite database a search for `řeka` will not find `Řeka` (searching `Řeka` does). MySQL is case-insensitive for all letters. PostgreSQL's `LIKE` is case-sensitive. It has only been tested on SQLite.
- Category names are unique according to the database's collation (case-insensitive on MySQL, case-sensitive on SQLite, so `Tech` and `tech` can coexist there).
- Search scans the posts table, which is fine for thousands of posts; for much larger data sets move to full-text search.
- There is no e-mail verification, password reset, or moderation/admin role, and categories can't be renamed or deleted. Because any logged-in user can create categories and posts without a rate limit, a malicious account could spam them, and spam categories can't be removed from the app.
- Sign up tells you when an email is already registered (the usual trade-off for a friendly form), and without verification anyone can register with someone else's address.
- Passwords need 8+ characters but are not checked against known-breached lists.
- Rate limits are per IP, so a botnet spreading guesses over many IPs is not stopped; add a WAF/fail2ban if you expect that.
