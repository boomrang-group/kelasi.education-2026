## 2026-03-30 - SQL Aggregation Consolidation in Dashboard Services
**Learning:** Multiple scalar `SELECT SUM(...) WHERE status = X` queries against the `paiement` table on dashboard loads cause unnecessary database roundtrips and multiple table scans.
**Action:** Use conditional SQL aggregation (`SUM(CASE WHEN ... END)`) to retrieve breakdown aggregates in a single database query.
