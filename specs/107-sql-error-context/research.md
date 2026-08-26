# Research: SQL / database error context

## Decision: Derive at render, do not persist Query facts

**Rationale**: `event.payload` is already canonical (`010`). Historical events become Query-aware without a backfill job. No extra columns/indexes for v1.

**Alternatives considered**: Promote SQLSTATE to `event_tag` or a column for search — deferred (spec non-goal).

## Decision: Precedence structured → breadcrumb → message

**Rationale**: BeaconBundle 1.8.0 `contexts.db` and `extra.sql` are explicit. Laravel/Sentry PHP put SQL inside `exception.value`. Doctrine opt-in breadcrumbs (`024`) are last-query hints when the exception string has no SQL (e.g. 1040).

**Alternatives considered**: Message-only parser — loses structured bindings. Breadcrumb-first — last successful query may not be the failing one; exception SQL wins.

## Decision: Innermost in-app frame for stack open + culprit

**Rationale**: Matches `FingerprintCalculator::topFrame` (walk `array_reverse` of payload frames). Payload order from BeaconBundle is outermost-first; Twig already reverses for display. Opening `loop.first` after reverse was the vendor throw site.

**Alternatives considered**: Outermost in-app (front controller) — worse for repositories. Keep vendor open — contradicts Sentry UX for this incident class.

## Decision: Culprit VARCHAR(255)

**Rationale**: Typical `App\Namespace\Class::method` exceeds 40 characters. 255 fits InnoDB defaults and FULLTEXT on `(title, culprit)` without dropping the index on MySQL. SQLite ignores length — skip ALTER.

**Alternatives considered**: 120 (spec minimum) — 255 is cheaper operationally than a second widen. TEXT — unnecessary for a list column.

## Decision: BeaconBundle 1.8.0 MINOR for `contexts.db`

**Rationale**: Additive Envelope field; no config flag required; works without `instrumentation.doctrine`. Scrub quoted literals (reuse SqlNormalizer idea, 8 KiB cap for context vs 200 for span descriptions).

**Alternatives considered**: Patch 1.7.9 — a new context object is a feature. Require doctrine instrumentation — misses PDO-only and connection failures.

## Decision: AI export additive `query` key, keep `beacon-ai-export/v1`

**Rationale**: Optional object; existing consumers ignore unknown keys. Avoids a format bump for one section.

**Alternatives considered**: v2 format — more churn than value.
