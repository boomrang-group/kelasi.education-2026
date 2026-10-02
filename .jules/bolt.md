# Bolt Performance Journal

## 2026-10-02 - Batch Prefetching for Nested Course/Lesson/Quiz Queries

**Learning:** Nested loops querying database records per course, per lesson, and per quiz (N+1 queries) caused ~100+ SQL queries on single page renders in student portal views (`customs/students/view/mes_cours.php`). Pre-fetching all lessons using `WHERE cours_id IN (...)`, all published quizzes for the class, and student submissions in a single query reduces query count to O(1) (~5 queries total), reducing page render overhead by ~95%+.

**Action:** When building or optimizing portal pages with multi-level hierarchies (e.g. Classes -> Courses -> Lessons -> Quizzes -> Submissions), batch-fetch all entities for the user/class at the top of the request and perform matching in PHP memory using pre-sorted maps.
