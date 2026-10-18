# Bolt Journal - Critical Performance Learnings

## 2026-03-01 - Consolidating Multiple Aggregations into Single Conditional Aggregation Query
**Learning:** In PHP dashboard services (such as `admin/service/dashboard-admin.php`), multiple `SELECT SUM(...)` queries filtering on different enum/status columns (e.g., Inscription, Minerval, Autre) on the same table (`paiement`) create unnecessary database roundtrips and force the database engine to scan/filter the table multiple times.
**Action:** Replace multiple scalar aggregation queries with a single query using `SUM(CASE WHEN statut = '...' THEN montant_paye ELSE 0 END)` conditional expressions. This reduces roundtrips from N to 1 and processes rows in a single pass.
