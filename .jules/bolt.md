## 2026-03-31 - Batching nested relational queries in PHP views
**Learning:** In hierarchical database relationships (e.g. Courses -> Lessons -> Contents -> Media details), fetching child and media records individually inside nested loops creates severe N+1 database queries ($O(N \cdot M \cdot K)$ roundtrips).
**Action:** Group parent IDs, fetch children in bulk using `WHERE foreign_id IN (...)`, index media by primary key in PHP associative arrays, and reconstruct the tree structure in memory to keep database queries down to $O(1)$ constant count regardless of dataset size.
