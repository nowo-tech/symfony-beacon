# Engineering audit (REQ-REV)

**App:** Symfony Beacon (`symfony-beacon`)  
**Pass date:** 2026-08-15 (first pass) · **Remediation High:** 2026-08-15 · **QA-002:** 2026-08-16 · **Platform 100% close:** 2026-08-17 · **Kit-over-shim pass 40:** 2026-08-29 (**v1.24.4**) · **AUTH-005 docs:** 2026-08-29 (**v1.24.5**)  
**Scope:** REQ-REV-002…007 (+ BP-004 evidence)  
**Verdict:** **✅ Pass** — Critical/High empty; Low backlog only (profiler CI budgets; residual kit chrome forks)

This document is the REV-007 evidence artifact. Prior Spec Kit hardening (`087`, `095`, `096`) feeds this gate. Re-run at least once per minor release train.

---

## Summary by dimension

| Dimension | REQ | Status | Notes |
| --------- | --- | ------ | ----- |
| Security | REV-002 | ✅ | Guards, CSRF, throttle, MM narrow API, encrypt; **AUTH-005** base `qr_login.mode: enabled` (local/PHPUnit/E2E); prod overlay `config/packages/prod/nowo_auth_kit.yaml` → `disabled` |
| N+1 / queries | REV-003 | ✅ | Hot-path inventory + eager/batch posture below; profiler CI budgets = Low |
| Refactor | REV-004 | ✅ | Rector/CS/PHPStan; QA-002 **100%**; kit forks inventoried + shrink started (PWA `install_links` removed) |
| Scalability | REV-005 | ✅ | Redis sessions/cache/Messenger; `compose.infra.yaml`; health; FrankenPHP docs |
| Architecture | REV-006 | ✅ | Module map + Spec Kit + ARCHITECTURE + BP-004 CI guard |
| Evidence / cadence | REV-007 | ✅ | This file |

**Overall REV-001:** ✅

---

## REQ-BP-004 (module boundary CI)

| Check | Evidence |
| ----- | -------- |
| Script | `.scripts/check-module-boundaries.sh` |
| Make / QA | `make check-module-boundaries` · part of `make qa` |
| CI | `.github/workflows/ci.yml` runs the script |
| Ownership bans | AdminProject ∈ `Project`; Identity ↛ `ProjectMembershipManager` / `ProjectCreationFormFactory`; OTLP ∈ `Ingest/Otlp`; Metrics ∈ `Ops`; Shared ↛ Issues/Performance write-path |
| LIKE + lazy | `App\Shared\Doctrine\SqlLikeEscaper`; `EXTRA_LAZY` on `Project` / `Issue` / Perf collections (script asserts Project + helper file) |
| Docs | `docs/CONTRIBUTING.md` §21; `specs/083` / `085` / `089` |

---

## Hot-path query posture (REV-003)

Committed inventory (eager / batch). Profiler absolute query-count CI gate remains **Low** (same posture as BP-v2 / Hector).

| Surface | Posture |
| ------- | ------- |
| Admin users directory | `UserRepository::findAllForAdminDirectory` — `createdBy` / `updatedBy` / `instanceRoles` eager |
| Admin groups | `UserGroupRepository` list/show — blame + memberships eager |
| Admin roles / permissions | `InstanceRoleRepository` / `InstancePermissionRepository` — blame + translations / permissions eager |
| Admin projects list | `ProjectRepository` — blame eager; detail memberships / groups / destinations / keys / thresholds hydrated on demand |
| Issue list / export / API | `IssueListQueryBuilderTrait` + assignee / `duplicateOf` `addSelect` |
| Issue show | `findOneByUuidHydrated` — assignee, duplicateOf, project |
| Issue history / comments | actor / author / assignee joins |
| Dashboard assignments | `findUsersByProjects` batch (no per-member N+1) |
| Member alerts / prefs | batch-load prefs / events / mentions |
| Volume thresholds | batched `COUNT` by scope (`027`) |
| Admin COUNT | `UserRepository::countAdmins` scalar (no full hydrate) |
| Collections rarely walked | `EXTRA_LAZY` on Project / Issue / PerfTransaction OneToMany |

---

## Kit Twig fork inventory (REV-004)

Strategy: `templates/kit/*_layout.html.twig` first; full page forks only for Administration chrome. Index: `templates/bundles/README.md`.

| Bundle | Host forks | Status |
| ------ | ---------- | ------ |
| UiKit | shell / burger / toasts / pagination | Intentional product chrome |
| Dashboard Menu / Breadcrumb / RoutingKit / HttpLog / CookieConsent admin / SiteBackup panel | Administration list/form chrome | Intentional until upstream hooks (`081`) |
| AuthKit | `layout` + thin `security/*` (`form/_fields`) | Thin FormKit painting |
| PWA | `install_prompt` only; Web Push via kit `web_push` + `WebPushPresentation` | **`install_links` fork removed** (2026-08-17); host SW/cookie shims removed (Pwa **1.5.0**, pass 40) |
| MaintenanceMode | public 503 + panel | Product chrome (`092`) |
| TwigBundle Exception / Nelmio Swagger | branded errors / shell | Product |

---

## Closed this train

| ID | Title | Resolution |
| -- | ----- | ---------- |
| SEC-001 | AUTH-005 QR enabled in prod default | Base `qr_login.mode: enabled` (UC-AUTH-21/22); `config/packages/prod/nowo_auth_kit.yaml` sets `disabled` (partial overlays must repeat `locale.*`) |
| REF-001 | QA-002 soft 35% vs platform 100% | Closed: PHP Clover **100.00%**; `COVERAGE_MIN=100` |
| N1-001 | Query budgets / profiler evidence | Closed as Medium: hot-path inventory above; profiler CI budgets → Low `N1-002` |
| REF-002 | Kit Twig forks unexplained | Closed as Medium: inventory + CONTRIBUTING; deleted PWA `install_links`; residual chrome → Low `REF-003` |
| BP-004 | Boundary CI incomplete vs matrix | Re-audited ✅ — script + QA/CI + SqlLikeEscaper / EXTRA_LAZY guards |

---

## Low backlog

| ID | Sev | Title | Notes |
| -- | --- | ------ | ----- |
| N1-002 | Low | Profiler query budgets in CI | Manual / Spec Kit later (parity with BP-v2) |
| REF-003 | Low | Shrink remaining kit page forks | Prefer upstream chrome hooks (`081`) |
| SCALE-001 | Low | Redis not in `/health/ready` | Optional |
| ARCH-001 | Low | ARCHITECTURE Redis / compose.infra | Ops docs cover it |

---

## How to re-verify

```bash
rg -n "mode:" -A1 config/packages/nowo_auth_kit.yaml config/packages/prod/nowo_auth_kit.yaml
# base: mode: enabled; prod overlay: mode: disabled

make check-module-boundaries
test ! -f templates/bundles/NowoPwaBundle/pwa/install_links.html.twig
```
