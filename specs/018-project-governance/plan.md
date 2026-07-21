# Implementation Plan: Project Governance

**Branch**: `018-project-governance`  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Technical Context

| Area | Decision |
|------|----------|
| Storage | Nullable columns on `project`: `retention_days`, `retention_max_events`, `ingest_rate_limit_per_minute`, `event_quota_daily`, `ingest_enabled` (bool default true) |
| Migration | `Version20260721163000` |
| Defaults | Empty UI field → `null` → inherit `beacon.*` params / env |
| Retention | `RetentionPurger` prefers project override then env |
| Rate limit | `IngestRateLimiter::accept($id, ?$override)`; Envelope uses effective project limit |
| Quota | `ProjectGovernanceResolver`; ingest `429` when exceeded; flash warning at ≥80% (session once) |
| Keys | POST revoke (`active=false`) / rotate (new key + deactivate old); hard cutover |
| UI | Project Settings governance section (owner/admin) |
| Audit | `ProjectApiKeyRevoked`, `ProjectApiKeyRotated` |

## Constitution Check

- Spec-first: yes (`018`)
- English docs/PHPDoc/UI strings
- PHPUnit: `AdminProjectsGovernanceTest`

## Out of scope

- Soft rotate grace period
- Platform ceiling caps beyond env defaults
- Digests for quota alerts
