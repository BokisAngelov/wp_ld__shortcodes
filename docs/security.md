# Security guide

What was reviewed, what was fixed, and how the automated scans work. These are copy-paste snippets for WordPress, so the guide also says what to check before pasting one into a site.

## Fixes made

| File | Problem | Fix |
|---|---|---|
| `force_login_redirect.php` | **Login lock bypass.** Any URL containing `/wp-json/` anywhere (for example `?x=/wp-json/`) skipped the redirect, exposing every page to logged-out visitors. | Removed the URI check (REST requests never reach `template_redirect`). `wp_safe_redirect()` used. |
| `custom_password_reset.php` | User enumeration ("No user found"), fatal on unknown user (`$user->ID` read before the check), no CSRF nonce, no throttling, forced `allow_password_reset` to true, unescaped HTML in the email. | Same message every time, nonce, throttle (5 per 15 min per IP, 1 per 2 min per account), WordPress reset rules respected, escaped email, header injection stripped. |
| `display_registered_users.php` | Any logged-in user, including a student, could list the name, email and country of every user. | Requires the `list_users` capability. Change it with the `display_registered_users_capability` filter. ACF null cases handled. |
| `force-import.php` | Import ran on a plain page load (CSRF), CSV values were echoed unescaped (XSS against the admin), could modify existing admin accounts, empty password allowed. | Button with nonce, everything escaped, privileged accounts skipped, random password when empty, email validated. |
| `ld_custom_certificate.php` | `cert_id` and `lesson_id` from the URL were not checked to be certificate and lesson posts, and course access was not checked. | Post type checks and `sfwd_lms_has_access()`. |
| `ld_cloudflare_registration.php` | An empty secret key still sent a request. | Fails closed with a clear error. |
| All | Direct execution of the file when the URL is requested. | `ABSPATH` guard (not for `force-import.php`, which is standalone on purpose). |

## Operating notes

- `force-import.php`: upload to the site root, open it as an admin, click *Run import*, then delete the script and the CSV. Keep the CSV outside the web root if you can, since it holds plaintext passwords.
- Turnstile keys belong in `wp-config.php` (`CF_TURNSTILE_SECRET_KEY`). Never commit them.
- The password reset throttle uses transients. With an object cache it is shared across requests as expected.
- If the site is behind a proxy or Cloudflare, `REMOTE_ADDR` is the proxy address, so the per-IP throttle becomes a global one. Map the real IP at server level (Cloudflare's real-IP module) if that matters.

## Automated checks

`.github/workflows/security.yml` runs on every push, pull request, weekly and on demand:

- **PHPCS + WordPress Coding Standards**, security sniffs only (escaping, nonces, input sanitising, safe redirects, SQL). Fails on any error.
- **Semgrep** with `p/php`, `p/security-audit`, `p/github-actions`.
- **Gitleaks** over the full history.
- **PHP lint** on 7.4, 8.0, 8.2, 8.3.
- **Dependabot** bumps the pinned GitHub Actions weekly.

Actions are pinned to commit SHAs and the workflow only has `contents: read`.

Silencing a finding: add `// phpcs:ignore Sniff.Name -- reason` on that line. Do not disable a sniff for the whole project.

## Rollback

`git revert` the security commit. That brings back the login-lock bypass and the other issues above.

## Not done / next

- No automatic tests for the shortcodes (they need a WordPress test environment).
- Enable in repo settings: private vulnerability reporting, secret scanning with push protection, Dependabot alerts.
