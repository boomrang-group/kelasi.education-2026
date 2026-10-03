# Bolt's Journal - Performance Learnings

## 2026-03-30 - Dashboard Financial Summary Query Consolidation
**Learning:** In multi-tenant dashboards (`admin` and `my_school` portals), financial totals by payment status (`Inscription`, `Minerval`, `Autres`) were fetched using multiple distinct `SELECT COALESCE(SUM(...))` queries on the `paiement` table. Consolidating these into a single query using SQL conditional aggregations (`SUM(CASE WHEN ... THEN ... ELSE 0 END)`) reduces table scans and database roundtrips by 66% without modifying return data structures.
**Action:** When computing aggregates for mutually exclusive categories or statuses in MySQL/PDO, use conditional aggregation in a single query instead of issuing multiple separate `SELECT` queries.
