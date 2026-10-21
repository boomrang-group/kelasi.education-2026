## 2025-05-18 - Batch Eager Loading for Course Content Hierarchy

**Learning:** `admin/nos_cours.php` rendered courses, lessons, and lesson media items by executing nested SQL queries in loops (O(courses * lessons * media items)). For a school with multiple courses and lessons, this resulted in hundreds of database queries per page load.
**Action:** When displaying nested data hierarchies (such as Course -> Lessons -> Lesson Contents -> Media Details), batch fetch IDs at each level and query child entities using `WHERE parent_id IN (...)`, then group and assemble the hierarchy in PHP memory.
