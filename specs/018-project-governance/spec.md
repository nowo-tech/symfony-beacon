# Feature Specification: Project Governance

**Feature Branch**: `018-project-governance`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Per-project retention, rate limits, and quotas configurable in Settings; revoke and rotate API keys; warn when approaching limits.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure retention, rate, and quota (Priority: P1)

As a project admin, I open Project Settings and set retention period, ingest rate limits, and storage/event quotas for this project.

**Why this priority**: Operators need self-serve control without editing global server config for every project.

**Independent Test**: Change settings; reload; assert persistence; verify ingest respects new limits in a controlled test.

**Acceptance Scenarios**:

1. **Given** I am a project admin, **When** I set retention days, rate limit, and quota, **Then** values persist and are shown on reload.
2. **Given** I am a non-admin member, **When** I open Settings, **Then** I cannot change governance fields (read-only or hidden).
3. **Given** invalid values (negative quota, zero retention where disallowed), **When** I save, **Then** validation errors are shown.

---

### User Story 2 - Revoke and rotate API keys (Priority: P1)

As a project admin, I revoke a compromised key and rotate to a new key without deleting the project.

**Why this priority**: Key hygiene is mandatory for self-hosted SaaS safety.

**Independent Test**: Create key; revoke; assert ingest with old secret fails; rotate; assert new secret works.

**Acceptance Scenarios**:

1. **Given** an active API key, **When** I revoke it, **Then** subsequent ingest using that key is rejected.
2. **Given** I rotate a key, **When** rotation completes, **Then** a new secret is shown once and the previous secret stops working after the documented cutover.
3. **Given** multiple keys, **When** I revoke one, **Then** other keys remain valid.

---

### User Story 3 - Approaching-limit warnings (Priority: P2)

As a project admin, I see warnings when usage approaches rate or quota limits so I can act before hard failures.

**Why this priority**: Prevents surprise ingest outages; depends on quota telemetry.

**Independent Test**: Seed usage near threshold; open Settings or dashboard; assert warning banner.

**Acceptance Scenarios**:

1. **Given** usage at or above the warning threshold (e.g. 80% of quota), **When** I view the project, **Then** an approaching-limit notice is visible to admins.
2. **Given** usage back below threshold, **When** I refresh, **Then** the warning is cleared.
3. **Given** hard limit exceeded, **When** ingest arrives, **Then** clients receive a documented rate/quota error (e.g. 429) consistent with existing ingest behaviour.

### Edge Cases

- Lowering retention does not imply immediate purge until the retention job runs (document timing).
- Rotating while clients still use the old key: document grace period or hard cutover.
- Concurrent rotate/revoke on the same key is serialized safely.
- Platform-wide ceilings may cap project settings (cannot exceed host policy).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Project Settings MUST expose per-project retention, rate limit, and quota controls for admins.
- **FR-002**: Ingest MUST enforce the project's rate and quota settings.
- **FR-003**: Admins MUST be able to revoke API keys so they can no longer authenticate ingest.
- **FR-004**: Admins MUST be able to rotate API keys and obtain a replacement secret.
- **FR-005**: System MUST surface approaching-limit warnings to project admins before hard enforcement.
- **FR-006**: Governance changes MUST be attributable (who changed what) where audit facilities exist.
- **FR-007**: Non-admin members MUST NOT modify governance or keys.

### Key Entities

- **Project governance settings**: Retention, rate, quota thresholds and warning ratio.
- **Project API key**: Secret material, status (active/revoked), rotation metadata.
- **Usage snapshot**: Current consumption toward rate/quota for warnings.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins can update retention/rate/quota and confirm persistence in under 1 minute.
- **SC-002**: Revoked keys fail ingest in acceptance tests with 100% rejection after revoke.
- **SC-003**: Rotated keys: new secret succeeds and old secret fails after cutover.
- **SC-004**: Warning appears whenever fixture usage crosses the configured approach threshold.

## Assumptions

- Complements Danger Zone (`011-project-danger-zone`) without replacing project delete/transfer flows.
- Default retention/rate/quota come from sensible server defaults when unset.
- Encryption of secrets continues via existing encrypt practices (`nowo-tech/doctrine-encrypt-bundle` where already used).
- Warning threshold defaults to 80% unless configured.
- Quotas in this feature are **daily** (and/or rate windows already documented); **monthly** caps are deferred to `032-monthly-quota`.

## Out of scope (deferred)

- Monthly event quota alongside daily → `032-monthly-quota`.
