## 2025-10-04 - Authentication Plaintext Fallback Removal
**Vulnerability:** In `login/index.php`, stored passwords under 60 characters were checked via direct plaintext comparison (`hash_equals($stored, $password)`), allowing unhashed passwords or raw string matches to authenticate.
**Learning:** Legacy authentication routines may include string length heuristic checks (`strlen($stored) < 60`) that bypass secure password hashing checks (`password_verify`).
**Prevention:** Always strictly use standard password hashing verification functions (`password_verify`) and never fallback to raw/plaintext password comparisons in authentication handlers.

## 2025-10-14 - Promoter Registration CSRF Protection
**Vulnerability:** In `new_school/index.php` and `new_school/register_process.php`, public promoter registration lacked CSRF token generation and validation, enabling potential unauthorized automated account registrations via cross-origin forged POST requests (V05).
**Learning:** Public registration endpoints are often omitted from CSRF protection routines because users are unauthenticated, but CSRF tokens are still necessary to bind registration submissions to genuine client sessions.
**Prevention:** Always pair multi-tenant onboarding forms with session-backed CSRF tokens using `service/security_helpers.php` (`csrf_input()` and `verify_csrf_token()`) and return early error responses on token mismatch.
