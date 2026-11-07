# Bolt's Performance Journal

Critical performance learnings and codebase-specific patterns.

## 2026-03-30 - Multi-Tenant Dashboard Query Consolidation with Native Prepared Statements

**Learning:**
In `admin/service/dashboard-admin.php`, 6 separate sequential database queries were previously executed to gather basic dashboard stats (classes count, teacher count, student count, and 3 category sums on `paiement`).
Consolidating these into 2 queries (one subquery scalar aggregation for counts, and one single-pass conditional `SUM(CASE WHEN...)` for payments) cuts query round-trips from 6 down to 2.
Crucially, because `database/db_connect.php` configures `PDO::ATTR_EMULATE_PREPARES = false`, repeating the same named parameter placeholder (e.g. `:ce`) across multiple subqueries in a single prepared statement triggers MySQL error `HY093: Invalid parameter number`. Using distinct placeholders (e.g. `:c1`, `:c2`, `:c3`) allows subquery aggregation without tripping native prepared statement limitations.

**Action:**
When consolidating subquery counts or multi-table stats into a single statement in this codebase, always assign unique parameter placeholder names (`:c1`, `:c2`, ...) rather than reusing `:ce` across subqueries.
