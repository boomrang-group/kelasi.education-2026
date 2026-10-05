## 2026-03-30 - SQL Conditional Aggregation for Dashboard KPIs
**Learning:** Multiple scalar `SUM()` queries filtered by different `statut` values on multi-tenant tables (such as `paiement`) cause redundant table/index scans for each stat card on dashboard page loads.
**Action:** Combine multiple filtered aggregate queries into a single query using conditional `SUM(CASE WHEN ... THEN ... ELSE 0 END)` statements to retrieve all metric totals in 1 DB round-trip.
