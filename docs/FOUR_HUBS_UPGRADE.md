# Four-Hub upgrade — review and deployment

Implemented on `feat/four-hubs-upgrade`, based on `53621c7`. No production database or remote branch was changed.

## User-visible behavior

- Home and navigation link to Knowledge, Assessment, Simulation and AI/Roadmap hubs.
- Knowledge has published articles/detail pages, keyword + RIASEC + existing career-group filters, external-link notices and administrator add/edit/hide/show forms.
- MBTI (12 questions) and EQ reflection (10 scenarios) are scored on the server, with account-specific browser drafts. Neutral MBTI axes use `X`, rather than inventing certainty. These questionnaires are explicitly unstandardized self-reflection tools.
- Assessment report combines latest Holland, MBTI, EQ and the existing career matrix. Existing login, profile, dashboard, Holland/result and classic simulation routes remain available.
- Simulation hub launches the existing Babylon.js lesson when `game/dist/index.html` is present. Persisted 2D and optional persisted 3D history share a timeline; 3D evidence remains qualitative.
- Roadmap combines available profile data, offers four practical stages and browser A4 print/PDF. Chat has server-side per-user calendar-day quota, duplicate-submit protection and a labeled no-key fallback.
- Assessment AI synthesis includes latest psychometrics and bounded 2D/3D activity. Chat sends bounded context and history, excluding direct account identifiers; user-facing notice explains external AI processing.

## Deliberate limits

- Game server sync remains disabled per `api/game/README.md`. Browser checkpoints/reports are not uploaded or treated as verified server evidence. This upgrade does not redesign the 3D campus or change the camera.
- The specification mixes “19 groups” and “19 careers”. Existing catalog/group mappings are retained and counts displayed dynamically. The referenced https://huongnghiepviet.com/nganh-nghe currently lists 23 top-level groups; this change does not invent 19 groups or claim complete coverage.
- Seed articles are explicitly evergreen guidance, not fabricated current admissions announcements. Administrators can publish sourced news.
- This change provides progress indicators and links; it does not implement a mandatory onboarding gate for Hub 1/2.
- No real paid OpenAI request, browser visual/print inspection, MySQL 5.7 or PHP 7.4 runtime test was performed. PHP validation ran on 8.3; code avoids PHP 8-only features in modified paths.

## Validation performed

- PHP syntax checks on all root, app, api/game and new test files.
- `php tests/psychometrics-test.php`: bank sizes, neutral results, reversed axes, bounds and malformed answer rejection.
- `php tests/four-hubs-data-test.php`: SQLite migration rerun/seed preservation, latest-result tie break, account isolation, optional 3D history, bounded skills, chat quota/fallback and cascades.
- Disposable MariaDB 10.11 bootstrap: original schema followed by the new migration twice, successful. Full network-connected MySQL application/concurrency tests remain to be run on staging.
- `python tests/four-hubs-http.py` against a disposable SQLite fixture: public and legacy routes, login, assessment saves, CSRF rejection, chatbot fallback, admin authorization and escaped article output.
- `cd game && npm test`: 6 files / 34 tests passed. `npm run build` passed, with an existing large-bundle warning.

## Deployment on cPanel

1. Back up the database and site files; test on a staging copy first.
2. Review the additive `database/migrations/2026_11_four_hubs_upgrade.sql`. Upload the PHP files, migration and updated `assets/css/app.css` together. Preserve the site's existing `config.php` and secrets.
3. The first request invokes `run_migrations()` and advances schema to 2.2.0 only after successful migration/seed. Upgrade from 2.1.0 does not reseed/overwrite existing career content. A failed upgrade responds 503 instead of silently rendering broken pages.
4. Build locally using `cd game && npm ci && npm run build`, then upload `game/dist/`. cPanel needs no Node runtime. The download bundle includes this build; build files remain excluded from Git.
5. Verify login, Hub 1 admin editing, all questionnaires, report, classic game, 3D launch, chat configured/unconfigured and A4 print. Confirm quota behavior with concurrent sessions on the staging MySQL server.
6. `health.php` now checks all four new tables. Production requires HTTPS; AI is optional and falls back when unavailable.

Rollback: restore the previous application files and CSS; leave additive tables intact to preserve new data. Do not drop tables or downgrade the schema marker without a reviewed recovery plan.
