## 2026-03-29 - Consolidated Dashboard SQL Queries

**Learning:** Dashboard metrics queries (counts and financial sums) in PHP services often executed multiple isolated PDO queries (`COUNT(*)`, `SUM(...)`) sequentially. Consolidating 4 scalar KPI count subqueries into 1 SELECT query and 3 payment status SUMs into 1 conditional aggregate query (`SUM(CASE WHEN ...)` ) reduces DB query roundtrips by >70% without altering business logic or output types.
**Action:** When inspecting dashboard service files, check for sequential scalar aggregate queries and batch them using scalar subqueries or `CASE WHEN` conditional aggregates.
