# Research: Identity book + error manual docs (114)

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Separate identity book | `docs/identity/` not under wiki dump | Brand rules ≠ operator screenshot catalog (REQ-DOCS-APP-005) |
| Error chapter number | `08-errors.md` after 00–07 | Continues product UI manual numbering from `111` |
| Transparent art scope | Runtime `public/**` only | Manual page PNGs are screenshots of full pages (opaque OK) |
| Style CSP | Nonce stamp on `<style>` | Parity with inline scripts; maintenance/error chrome uses inline CSS |
| Mailpit | Opt-in Make target | Keeps default smoke / CI shards free of mail profile |
| Legal | Document counsel pending | REQ-CC-010 must not claim production clearance |
| Capture hygiene | Filter dashboard + clear MM schedule + force setup EN + admin Groups demo row | Docs must not ship E2E XSS titles, 2099 countdowns, or Playwright group names |
| Footer locale | Sub-request sync + explicit `_legal_footer` `|trans` locale | Fragment `render(controller)` left shared Translator on DEFAULT_LOCALE |

## Alternatives considered

| Option | Rejected because |
|--------|------------------|
| Dump brand assets only in wiki | Duplicates / drifts from product docs; harder for Packagist consumers |
| Require Mailpit in default smoke | Slows CI; flakes when mail profile down |
| Opaque RGB error illustrations | Breaks dark/light page blend; fails art QA |
| Client-only fake countdown on maintenance shot | Prefer clearing real schedule so operators see truthful preview |
| Ignore Spanish footer when menus look English | Menus/DB can stay EN while Translator desyncs — must fix product locale |
| Leave E2E group rows in admin-groups.png | Docs look like a test DB; filter/seed a human demo row |

## Open follow-ups

- Counsel replaces legal placeholders (REQ-CC-010) — tracked in LEGAL-AND-COOKIES, not this feature’s ship gate.
- Optional: regenerate identity preview images when mark SVG changes (manual / Make doc only).
- Optional: ensure-e2e-env should not rewrite cold `DEFAULT_LOCALE` away from `en`.
- Done (pre-tag polish): app footer legal locale (sub-request Translator sync + explicit footer `|trans` locale); admin-groups docs filter/`Beacon operators` + Makefile E2E group purge.
