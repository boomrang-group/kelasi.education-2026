## 2025-05-18 - Consolidate scalar queries in dashboard aggregation
**Learning:** Combining multiple scalar aggregate queries (`COUNT` across multiple tables or conditional `SUM` across different column values on the same table) into a single SELECT statement reduces PDO database roundtrips from 6 to 2 and eliminates redundant table scans.
**Action:** When fetching dashboard metrics or KPI summaries, combine entity counts using subqueries and financial status sums using `SUM(CASE WHEN ... THEN ... ELSE 0 END)`.
