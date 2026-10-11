# Bolt's Performance Journal

## 2025-05-18 - Consolidating redundant teacher class lookup queries
**Learning:** In `customs/teacher/view/mes_cours.php`, `findClassIdsForTeacher` executed up to 6 distinct `PDO::prepare` and `execute` calls sequentially to match teacher classes by ID, username, and email. Combining these OR predicates into a single parameterized query with a subquery reduces SQL roundtrips by 83% (from 6 queries to 1) per page load.
**Action:** When finding multi-attribute matches (like user ID OR username OR email), build a single dynamic `WHERE (cond1 OR cond2 OR cond3)` clause with parameterized bindings rather than executing sequential queries and array-merging in PHP.
