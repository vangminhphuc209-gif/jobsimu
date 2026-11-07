---
name: php-save-sync
description: Manages secure PHP session authentication, CSRF tokens, optimistic revisions, idempotency keys, and event sequencing.
---

# skill:php-save-sync

## Purpose
Ensures atomic, anti-cheat, and tamper-resistant state persistence between the client-side Babylon.js simulation and the MySQL database backend.

## Security & Protocol Requirements
1. **Origin & Authentication:** Same-origin execution sharing `$_SESSION['user_id']`. Requests without active session return `401 Unauthorized`.
2. **CSRF Protection:** Header `X-CSRF-Token` required on all `POST` requests, validated against session token.
3. **Optimistic Revision Tracking (`revision`):** Every save snapshot increments `revision`. If the incoming revision does not match `server_revision + 1`, a conflict `409 Conflict` is returned to prevent overwriting parallel state.
4. **Idempotency Key (`idempotency_key`):** Prevents duplicate event insertion or duplicate save states on unstable network retries (`QA-10`).
5. **Event Sequence Ordering (`event_seq`):** Game actions and events are sequentially ordered. Out-of-order actions or corrupted sequences are rejected.
6. **No Client Score Manipulation (`QA-09`):** Server computes career telemetry strictly from recorded event logs and actions. Client attempts to pass arbitrary `score` payloads are filtered and discarded.
