## 2025-05-18 - Batch-loading nested relational hierarchies (Course -> Lessons -> Media)

**Learning:** In PHP/PDO views like `admin/nos_cours.php`, nesting `SELECT` queries inside `foreach` loops for multi-level relationships (Courses -> Lessons -> Lesson Contents -> Media Metadata) causes severe N+1 query multiplication (e.g., 1 + N + N*M + N*M*K database roundtrips).

**Action:** Replace nested query loops with a batching approach:
1. Gather parent IDs (`array_column`, `array_map('intval', ...)`).
2. Fetch child records using `WHERE parent_id IN (...)`.
3. Group children into associative arrays indexed by parent ID (`$childrenByParent[$pid][] = $child`).
4. Collect media/content IDs by type and batch query metadata (`SELECT ... WHERE id IN (...)`).
5. Assemble the final nested structure in memory.
This reduces query overhead from hundreds of queries to a constant maximum (e.g. 7 queries total).
