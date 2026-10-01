## 2026-03-30 - Legacy Plaintext Password Fallback Removal
**Vulnerability:** In `login/index.php`, passwords were being compared using `hash_equals($stored, $password)` as a fallback whenever `$stored` was shorter than 60 characters.
**Learning:** Legacy authentication fallback code introduced to support older plaintext password records bypassed standard `password_verify` checks and allowed logging in with unhashed strings.
**Prevention:** Strictly enforce `password_verify()` across all authentication routines and avoid legacy raw string comparison fallbacks in password validation logic.
