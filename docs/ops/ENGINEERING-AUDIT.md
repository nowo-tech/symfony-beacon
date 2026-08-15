# Engineering audit (REQ-REV)

**App:** Symfony Beacon (`symfony-beacon`)  
**Pass date:** 2026-08-15 (first pass) · **Remediation High:** 2026-08-15  
**Scope:** REQ-REV-002…007  
**Verdict:** **✅ Pass** — Critical/High empty; Medium/Low backlog (soft QA-002, kit forks)

This document is the REV-007 evidence artifact. Prior Spec Kit hardening (`087`, `095`, `096`) feeds this gate. Re-run at least once per minor release train.

---

## Summary by dimension

| Dimension | REQ | Status | Notes |
| --------- | --- | ------ | ----- |
| Security | REV-002 | ✅ | Guards, CSRF, throttle, MM narrow API, encrypt; **AUTH-005** default `qr_login.mode: disabled` (enabled only `when@dev`/`when@test` for E2E) |
| N+1 / queries | REV-003 | ⚠️ | Admin list repos eager/batch; no profiler budgets (Medium) |
| Refactor | REV-004 | ⚠️ | Rector/CS/PHPStan; QA-002 soft **35%**; kit Twig forks |
| Scalability | REV-005 | ✅ | Redis sessions/cache/Messenger; `compose.infra.yaml`; health; FrankenPHP docs |
| Architecture | REV-006 | ✅ | Module map + Spec Kit + ARCHITECTURE |
| Evidence / cadence | REV-007 | ✅ | This file |

**Overall REV-001:** ✅

---

## Closed this train

| ID | Title | Resolution |
| -- | ----- | ---------- |
| SEC-001 | AUTH-005 QR enabled in prod default | `qr_login.mode: disabled` + `when@dev` / `when@test` → `enabled` for UC-AUTH-21/22 |

---

## Medium / Low backlog

| ID | Sev | Title | Notes |
| -- | --- | ------ | ----- |
| N1-001 | Medium | Query budgets / profiler evidence | Seeded fixtures |
| REF-001 | Medium | QA-002 soft 35% vs platform 100% | Product waiver or raise floor |
| REF-002 | Medium | Kit Twig forks | Shrink (`081`) |
| SCALE-001 | Low | Redis not in `/health/ready` | Optional |
| ARCH-001 | Low | ARCHITECTURE Redis / compose.infra | Ops docs cover it |

---

## How to re-verify

```bash
rg -n "qr_login:" -A6 config/packages/nowo_auth_kit.yaml
# default mode: disabled; when@dev / when@test: enabled
```
