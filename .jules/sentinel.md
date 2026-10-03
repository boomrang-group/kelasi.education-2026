## 2025-10-03 - Plaintext Password Fallback in Authentication Logic
**Vulnerability:** `login/index.php` permitted plaintext password checks using `hash_equals($stored, $password)` if the stored password length was less than 60 characters.
**Learning:** Legacy system migrations sometimes leave weak fallback checks in login scripts, bypassing secure hashing functions (`password_verify()`).
**Prevention:** Always enforce standard password hashing functions (`password_verify`) and reject raw password comparisons in authentication handlers.
