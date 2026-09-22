# Security Review

## Scope

This review covers the authentication, authorization, CSRF, database access,
session and cookie configuration, HTTP headers, and the live-search path.

## Findings

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| 1 | 🟠 HIGH | `Public_html/webbanhang/database/config.php` | 2-5 | The application uses the MySQL `root` account with an empty password. This gives the application administrative database privileges and is unsafe for a public deployment. | 9/10 |

### Finding 1: Root database account with empty password

The application connects through `database/dbhelper.php` using credentials
defined in `database/config.php`. If the application or host is compromised,
these credentials provide administrative access to the database.

Recommended remediation:

- Create a dedicated database user for the application.
- Use a strong, non-empty password.
- Grant only the privileges required by the application.
- Store credentials in environment variables or a configuration file outside
  the web root.
- Do not use the MySQL `root` account from the application.

## Existing positive controls

- CSRF tokens are generated with `random_bytes(32)` and checked with
  `hash_equals()` in `Private/security/csrf.php`.
- Session cookies use `HttpOnly`, `Secure`, and `SameSite=Lax` in
  `Private/security/session.php`.
- Login throttling records failures and limits repeated attempts in
  `Private/security/auth_throttle.php`.
- Passwords are processed with `password_hash()` and `password_verify()`.
- Prepared statements are used in authentication, order, cart, and search
  database paths.
- Admin pages check the authenticated session and administrator role.
- `.htaccess` configures HSTS, `X-Content-Type-Options: nosniff`, and
  `X-Frame-Options: SAMEORIGIN` when HTTPS is active.
- `assets/js/search.js` assigns user-controlled result values with
  `textContent` rather than `innerHTML`, and URL-encodes the search query.
- `utils/search_api.php` uses a prepared statement for the search query.

## Hardening recommendations

These items were not confirmed as directly exploitable during this review:

- Configure HTTPS enforcement for the real production hostname rather than
  only `webbanhang.local`.
- Add a production Content Security Policy and `Referrer-Policy`.
- Keep private security and configuration files outside the document root where
  possible.
- Keep detailed database errors in server logs and show only generic errors to
  users.
- Enable strict session settings such as `session.use_strict_mode=1` and
  `session.use_only_cookies=1`.
