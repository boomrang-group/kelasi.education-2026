## 2025-10-04 - Authentication Plaintext Fallback Removal
**Vulnerability:** In `login/index.php`, stored passwords under 60 characters were checked via direct plaintext comparison (`hash_equals($stored, $password)`), allowing unhashed passwords or raw string matches to authenticate.
**Learning:** Legacy authentication routines may include string length heuristic checks (`strlen($stored) < 60`) that bypass secure password hashing checks (`password_verify`).
**Prevention:** Always strictly use standard password hashing verification functions (`password_verify`) and never fallback to raw/plaintext password comparisons in authentication handlers.
