# Game synchronization status

The playable teacher lesson currently uses a validated local checkpoint in the
browser. It does **not** create a server run or claim cross-device synchronization.
All legacy Game API endpoints return HTTP 503 with `GAME_SYNC_UNAVAILABLE` from
`bootstrap.php`, before loading the PHP application or opening a database.

This is an intentional fail-closed gate. The previous implementation allowed run
access without owner checks, accepted arbitrary action IDs and client-provided
NPC mutations/evidence, and counted skipped feedback as collected feedback.
Merely obtaining a CSRF token would not make that implementation safe.

Do not remove the gate or replace it with an environment toggle until a server
implementation provides and tests:

- Authentication and ownership validation for every read/write, with 401/404 cases.
- A same-origin authenticated CSRF initialization flow before the first POST.
- Server-owned action definitions, prerequisite validation and state transitions;
  reject arbitrary client scores, evidence and mutations.
- Atomic event ordering, revision conflicts, idempotent retries and transaction
  rollback, including concurrent requests and lost-response retries.
- Evidence-based reports that distinguish skipped feedback from collected work.
- Contract alignment with the playable `LessonSession`, migrations tested against
  a disposable MySQL instance, and end-to-end save/resume/network-loss tests.

`game/src/api/ApiClient.ts` is retained as an adapter for that future integration.
It rejects missing CSRF and fabricated local run IDs, preserves nested deployment
paths and stable snapshot retry identities, and surfaces malformed JSON, timeout,
network and server errors. It is not used as a successful-sync fallback.
