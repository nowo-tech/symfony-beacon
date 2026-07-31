# Research: GDPR User Export and Anonymize

## Decision: App-owned anonymize (not anonymize-bundle runtime)

**Decision**: Implement `AccountAnonymizer` in Beacon. Do not require `nowo-tech/anonymize-bundle` for production account scrubbing.

**Rationale**: ROADMAP 6.8 — anonymize-bundle is for staging dumps / test fixtures, not the live account executor.

**Alternatives considered**: Wire anonymize-bundle as executor → conflicts with stated product policy.

## Decision: JSON download (`beacon-account-export/v1`)

**Decision**: Synchronous `application/json` attachment (same pattern as AI issue export). No ZIP in v1.

**Rationale**: Spec allows archive/JSON; JSON is enough for account + memberships metadata and easiest to test.

## Decision: Soft-delete = disable + scrub + `anonymized_at`

**Decision**: Set UserKit `enabled=false`, replace email/displayName, rotate password to random hash, clear reset tokens and password history, delete social links and push subscriptions, set `anonymized_at`. Keep row for FK integrity (assignee/comments).

**Rationale**: Hard-delete breaks issue assignee/comment FKs; soft path matches SC-002 (cannot log in with old credentials).

## Decision: Block sole owner / last admin

**Decision**: Refuse anonymize when the user is the sole direct Owner of any project, or the last `ROLE_ADMIN`.

**Rationale**: Same safety as membership removal / admin toggle; avoids orphan projects and lockout.

## Decision: Session invalidation for self only

**Decision**: On self-service anonymize, invalidate session and redirect to login. For admin anonymizing another user, email/password/disable is sufficient (remember-me is signature-based; next request fails UserChecker).

**Rationale**: No shared session store per user ID without extra infrastructure.
