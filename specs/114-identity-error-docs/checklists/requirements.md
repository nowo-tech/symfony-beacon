# Checklist: Identity book + error manual (114)

## Spec quality

- [x] User stories map to identity, error chapter, art format, CSP, markup, Mailpit, capture hygiene, footer locale
- [x] Non-goals exclude counsel-approved legal copy and Mailpit-in-default-smoke
- [x] Cross-refs to `063`, `111`, OTHER REQ-DOCS-APP-005 / REQ-ERROR-001 / REQ-CC-010

## Implementation readiness

- [x] Identity book paths and indexes listed
- [x] Capture paths for `/_error/*` and maintenance preview listed
- [x] Transparent PNG rule scoped to runtime assets only
- [x] Make / E2E lanes distinguished (smoke vs mailpit)
- [x] Capture hygiene: dashboard filter, MM schedule clear, cold setup EN, admin Groups filter
- [x] Footer locale: sub-request sync + explicit `_legal_footer` `|trans` locale (FR-014)

## Close-out

- [x] ROADMAP Phase 6.66
- [x] Amend `063` FR-002 and `111` chapter list (+ FR-015/016/017 hygiene)
- [x] `.specify/feature.json` → `114-identity-error-docs`
- [x] Status: Implemented in tree (Unreleased)
