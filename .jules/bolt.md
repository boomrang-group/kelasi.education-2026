# Bolt's Journal - Critical Learnings

## 2025-05-18 - Nested N+1 Query Patterns in Course/Lesson Data Fetching
**Learning:** In MyKelasi PHP files (like `admin/nos_cours.php`), hierarchical models (cours -> lecons -> lecon_contenus -> media tables) were fetching children via SQL queries nested inside loops. For schools with tens of courses, this generates hundreds of round-trip PDO queries per page load.
**Action:** When fetching relational hierarchies, batch load IDs using `array_column` and query with `WHERE parent_id IN (...)`, then group into PHP arrays indexed by parent ID before reassembling the structure.
