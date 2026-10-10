## 2025-10-04 - Authentication Plaintext Fallback Removal
**Vulnerability:** In `login/index.php`, stored passwords under 60 characters were checked via direct plaintext comparison (`hash_equals($stored, $password)`), allowing unhashed passwords or raw string matches to authenticate.
**Learning:** Legacy authentication routines may include string length heuristic checks (`strlen($stored) < 60`) that bypass secure password hashing checks (`password_verify`).
**Prevention:** Always strictly use standard password hashing verification functions (`password_verify`) and never fallback to raw/plaintext password comparisons in authentication handlers.

## 2025-10-04 - Database Exception Information Leak Prevention
**Vulnerability:** In `database/db_connect.php`, connection errors caught in the `PDOException` block outputted `$e->getMessage()` directly via `die()`, exposing database connection details (e.g. host, credentials, database name) in production.
**Learning:** Exception handling routines that use `die()` or `exit()` with `$e->getMessage()` can leak internal system configuration and credentials to end users upon database connectivity failures.
**Prevention:** Always log detailed exception messages using `error_log()` and display generic error messages to clients unless `DEBUG` mode is explicitly enabled.
