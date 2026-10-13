## 2025-10-14 - Consolidate Multiple Aggregate Queries in Dashboard Services

**Learning:** Aggregating metrics across status breakdown (e.g., Inscription, Minerval, Autre) using multiple `SELECT SUM(...) WHERE status = ...` queries causes unnecessary DB roundtrips and multiple full/index table scans. Using conditional aggregation (`SUM(CASE WHEN ... THEN ... ELSE 0 END)`) in a single SQL query reduces DB network overhead by 66% and scans the table once.

**Action:** Whenever calculating metrics split by categories or status in backend service handlers (like dashboard services), combine them into a single conditional `SUM(CASE ...)` SQL query.
