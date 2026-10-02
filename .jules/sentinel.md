## 2026-03-31 - Remove Insecure Legacy Plaintext Password Comparison in Login
**Vulnerability:** `login/index.php` allowed logins with raw plaintext strings via `hash_equals($stored, $password)` if the stored password string length was under 60 characters.
**Learning:** Legacy fallbacks intended to handle unhashed database values create critical broken authentication vulnerabilities that bypass standard hashed password checks (`password_verify`).
**Prevention:** Strictly enforce `password_verify()` for all user password checks and require password reset flows through verified out-of-band channels for non-hashed legacy accounts.
