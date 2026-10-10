## 2025-05-18 - Batch Resolving Message Sender Labels in Chat Endpoints

**Learning:** In `admin/messages.php`, the message list endpoint (`?api=fetch`) executed individual SQL queries inside a `foreach` loop over up to 500 messages to resolve sender display names via `labelFromSender`. Since clients poll this endpoint every 5 seconds, this generated up to 501 SQL queries per poll.
**Action:** Use batch preloading (`batchResolveSenderLabels`) with parameterized `IN (...)` queries and static memoization to fetch distinct teacher and student sender names in at most 2 queries total per fetch request.
