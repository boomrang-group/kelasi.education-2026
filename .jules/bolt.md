# Bolt's Performance Journal

## 2025-10-04 - Dashboard SQL Query Consolidation & Native Prepared Statements
**Learning:** In dashboards querying multi-tenant MySQL schemas with `PDO::ATTR_EMULATE_PREPARES = false`, multiple sequential `COUNT(*)` or `SUM(CASE ...)` queries create redundant network roundtrips and multiple table scans on large tables (like `paiement`). When consolidating multiple subqueries in a single prepared statement, MySQL native prepares reject duplicate parameter names (`HY093: Invalid parameter number`), so placeholders must be distinct (e.g. `:c1`, `:c2`, `:c3`).
**Action:** Consolidate individual scalar counts into single subquery rows with unique placeholder names, and collapse multi-status aggregations into single-pass conditional `SUM(CASE WHEN ...)` aggregations over the filtered table.
