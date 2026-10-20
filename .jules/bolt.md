# Bolt's Performance Journal

## 2026-03-30 - SQL Query Consolidation via Conditional Aggregation & Subqueries
**Learning:** Dashboard metric aggregations in multi-tenant PHP apps often perform multiple round-trip SQL queries to compute totals per category (e.g. Inscription vs Minerval vs Autres). Scanning `paiement` 3 separate times or executing 3 distinct queries per page load creates unnecessary query overhead and network latency.
**Action:** Consolidate multi-category SUM/COUNT queries into a single SQL statement using conditional aggregation (`SUM(CASE WHEN ... THEN ... ELSE 0 END)`) or scalar subqueries in `SELECT`.
