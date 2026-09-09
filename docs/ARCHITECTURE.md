# Architecture

ModInspect is a lightweight PHP 8.1+ MVC app.

Current master blueprint: `ModInspect_Master_Blueprint_v1.1.md`. Historical v1.0 is preserved.

Implemented structure:

- `public/index.php`: route registration and dispatch.
- `app/Core`: router, DB connection, controller base, CSRF.
- `app/Controllers`: public, module, admin request handlers.
- `app/Models`: PDO-backed read/write queries.
- `app/Services`: deterministic business rules.
- `app/Views`: Bootstrap-like PHP templates.
- `database/schema.sql` and `database/seed.sql`: base schema and seed data.
- `database/migrations`: additive SQL patches.

Phase 0 collection foundation:

- Provider contract: `App\Contracts\SearchProviderInterface`.
- Mock provider: fixture-only candidate generation.
- Rule extractor: deterministic extraction from listing text.
- Product resolver: alias/name matching.
- Validator: Green/Amber/Red lane classification with auto-accept disabled.
- CLI dry run: `cli/collect.php --mock --dry-run`.
- DB-backed mock run: `cli/collect.php --mock --query=5700x3d`.
- Migration runner: `cli/migrate.php database/migrations/file.sql`.
- Snapshot runner: `cli/snapshot.php`.
- Snapshot reproduction: `cli/reproduce_price_snapshot.php --snapshot-id=123`.
- Coverage Planner: `app/Services/Collection/CoveragePlanner.php`.
- Coverage CLI: `cli/coverage.php --dry-run` or `cli/coverage.php --create-jobs`.

No live provider is enabled by default.

Admin authentication and authorization:

- `AuthController` owns login/logout routes.
- `AuthService` owns local account authentication, password verification, session lifecycle, login attempt recording, user creation, and auth audit events.
- `AuthorizationService` owns static role-to-permission mapping.
- `AdminController` enforces permissions server-side before rendering protected pages or executing protected POST actions.
- Admin views may hide unavailable actions, but hidden UI is not an authorization boundary.
- Authenticated human actions should pass actor identity into review corrections, review decisions, source controls, incidents, and audit logs.

Current roles:

- `admin`: full current Admin access.
- `reviewer`: review queue, corrections, review decisions, and read access to operational/product/snapshot context.
- `operator`: collection/source operations and read access to operational/product/snapshot context; cannot approve observations.

Phase 3A price snapshots:

- New snapshots are immutable calculation artifacts.
- New snapshots persist formula/cohort/quartile/confidence method versions.
- New snapshots persist a deterministic calculation hash and compact manifest.
- `price_snapshot_observations` records considered and included observation membership with exclusion reasons.
- Phase 3E confidence is deterministic and versioned as `confidence-v1`.
- Snapshot confidence is a data-quality signal built from sample size, freshness, source diversity, and evidence quality. It never changes observed prices or quartile outputs.
- Confidence components, source distribution, freshness metrics, reason codes, weights, and policy caps are preserved in the snapshot manifest for offline reproduction.
- Legacy snapshots are marked as provenance unavailable instead of receiving inferred membership.

Phase 3F review operations:

- Review analytics are derived from persisted review, correction, observation, source, and job lineage.
- Clean-Green precision is measured as Green records approved without material correction divided by reviewed Green records.
- Green approved after correction is tracked separately.
- Source Health and Review Quality are separate domains: health asks whether collection functions; review quality asks whether collected data is useful.
- Fixture/mock metrics can establish an engineering baseline but cannot produce `READY_FOR_SCALE`.

Future approved architecture:

- Community Data Feedback Loop is approved but not implemented.
- Community submissions are evidence/signals, not votes.
- Community reports may feed review priority and future Coverage Planner priority.
- Community reports must never directly set `price_indices`, `price_histories`, or public market snapshots.
- Admin accepts evidence/observations; the deterministic Price Engine recalculates snapshots.

See `docs/decisions/ADR-005-community-feedback-is-evidence-not-voting.md`.
