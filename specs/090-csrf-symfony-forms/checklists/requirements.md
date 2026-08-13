# Requirements checklist: CSRF via Symfony Forms (`090`)

**Purpose**: Validate specification completeness and as-built readiness  
**Created**: 2026-08-11  
**Updated**: 2026-08-12  
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] Spec captures CSRF-only helper + named Types + GET filter migration
- [x] GET filter contract notes: `required` false except `per_page`; CSRF off intentional; optional fields ≠ authz weaken
- [x] Cross-links `077` deferred hand-rolled forms
- [x] Non-goals list AJAX header CSRF + kit vendor modal tokens
- [x] Dashboard title weight polish noted under Scope UX
- [x] plan.md + tasks.md present (full Speckit set)

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] FR-001…FR-008 testable; Settings F7 marked delivered
- [x] Success criteria measurable (SC-001…SC-004)
- [x] Acceptance scenarios for US1–US4 defined
- [x] Edge / exception cases identified (AJAX, logout, kits)

## Feature Readiness

- [x] Status → Implemented (as-built); host HTML mutable POSTs migrated
- [ ] ROADMAP Phase 6.39 marked **Done** on release cut (prep: as-built Done in ROADMAP table)
- [x] Related specs (`077`, `081`, …) point at `090` / kit chrome as needed
