# Bolt Journal

## 2025-05-18 - Batching dashboard queries and memoizing teacher lookup
**Learning:** In MyKelasi's portal dashboards (e.g. `customs/teacher/view/dashboard.php`), rendering KPIs and stats previously executed multiple separate `SELECT COUNT(*)` queries across media tables (`pdfs`, `videos`, `audios`) and duplicate student queries (`students`). Batching count queries via `UNION ALL` cuts DB round-trips from 3 to 1, while static memoization of `buildTeacherWhere()` avoids repeating `teacher` email lookups.
**Action:** Look for portal dashboards making multiple independent `COUNT(*)` or filter lookup queries and consolidate them with `UNION ALL` or static request-level memoization.
