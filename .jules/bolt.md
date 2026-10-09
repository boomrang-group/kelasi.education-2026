## 2025-10-10 - Deduplicate bound parameter IDs for SQL IN clauses
**Learning:** Extracting relational entity IDs into arrays for batch `WHERE id IN (...)` queries can result in duplicate IDs when multiple child records reference the same entity. Passing duplicates creates redundant SQL placeholders and array elements. Using `array_values(array_unique($ids))` ensures clean 0-indexed sequential arrays for PDO position parameter binding.
**Action:** Always deduplicate ID lists with `array_values(array_unique(...))` before dynamically building `IN (?, ?, ...)` PDO SQL queries.
