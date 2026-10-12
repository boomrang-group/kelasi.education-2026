## 2025-05-18 - Consolidate Multiple Sum Aggregations into Single Conditional Aggregation Query
**Learning:** Separate scalar queries (`SELECT SUM(...) WHERE status=X`) over the same table can be consolidated into a single query using conditional aggregation (`SUM(CASE WHEN status=X THEN amount ELSE 0 END)`), reducing database round-trips from N to 1.
**Action:** When querying breakdown statistics from a common table (e.g., payments/orders/financials), combine queries using conditional SUM/CASE expressions.
