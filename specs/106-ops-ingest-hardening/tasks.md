# Tasks: Ops ingest hardening

**Feature**: `106-ops-ingest-hardening`  
**Status**: Implemented (Unreleased / Phase 6.58)

## Phase 1: Messenger, compose, metrics

- [x] T001 Move `MessengerQueueHealth` to `App\Ops\Messenger`; count Redis `async_ingest` / `async` / `failed`; Doctrine fallback
- [x] T002 Ops overview failed-transport depth + i18n; `/metrics` `beacon_messenger_failed_pending`
- [x] T003 Drop `/messages` from prod Compose Messenger Redis DSN defaults

## Phase 2: Ingest + retention

- [x] T004 `EventQuotaUsageStore` daily/monthly `cache.app` seed + increment
- [x] T005 `ProcessEnvelopeHandler` one unique-constraint retry after `EntityManager::clear()`
- [x] T006 `RetentionPurger` batched delete (1000); `docs/ops/EVENT-STORAGE.md`

## Phase 3: Security + kits

- [x] T007 `PrivateNetworkTarget` shared by Mercure hub + outbound webhook guards
- [x] T008 `app:project:api-key-legacy-secrets` dry-run / `--apply` redundant `secret_key` only
- [x] T009 AuditKit `TimestampableTrait` on member-alert/push entities
- [x] T010 FormKit **2.5.2**; Dashboard Menu **2.1.10** (kit tags `SearchQueryType`; drop host override)

## Phase 4: QA + specs

- [x] T011 Empty PHPStan baseline; injectable FrankenPHP seams; drop query-trait `require-extends`; Rector vs CS-Fixer ownership
- [x] T012 Restore includable PHPUnit 100%; PHPStan-clean tests without ignores
- [x] T013 Spec `106`; amend `018` / `032` / `033` / `035` / `038` / `081` / `084` / `087` / `091` / `094` / `095` / `096` / `099` / `105`; ROADMAP **6.58**; `feature.json`
