# symfony-beacon Constitution

## Core Principles

### I. Spec-First (NON-NEGOTIABLE)

No feature is implemented without going through the Spec Kit SDD flow:
**Constitution → Specify → (Clarify) → Plan → Tasks → (Analyze/Checklist) → Implement**.
Artifacts under `specs/` are the source of truth; code must align with them.

### II. Canonical stack

- **PHP 8.5** via `dunglas/frankenphp:1-php8.5`
- **Symfony 8.1.\*** (Flex + native Runtime)
- **Docker Compose** as the required local environment
- FrankenPHP in **classic** or **worker** mode, switched only via `FRANKENPHP_MODE`
- **MySQL 9.7**, Symfony Messenger (async ingest), Pentatrion Vite + **Tailwind CSS**
- Modular Symfony layout under `src/{Identity,Project,Ingest,Issues,Performance,Analytics,Native,Shared}`
- Prefer official `nowo-tech/*` kits for auth/users/cookies/menus where applicable (AuthKit, cookie-consent, etc.)
- Progressive Web App via `nowo-tech/pwa-bundle` for browser installability
- Hotwire Native support via `symfony/ux-native` + `symfony/ux-turbo` (server-side shell contract; see `specs/008-ux-native`)

Do not introduce alternate stacks (Nginx+FPM, Apache) without amending this constitution.
Do not introduce full DDD/hexagonal layers; keep Symfony modular conventions.
Do not replace the Twig app with a separate mobile-only UI stack without a new spec amending this principle.

### III. Product mission

**symfony-beacon** is a self-hosted error-tracking server for PHP/Symfony that is **wire-compatible** with the Envelope ingest protocol so existing SDKs and the Symfony client (`nowo-tech/beacon-bundle`, separate repository, DSN = host/port/project) can send events without a SaaS account.

Operators may expose the same Twig UI to **native iOS/Android shells** (Hotwire Native) and/or browsers (PWA). Store-client source trees are optional companion projects; this repository owns the server contract.

### IV. Docker-first

Any runtime, PHP extension, or Caddy change must live in `Dockerfile`, `.docker/`, or `compose*.yaml`.
Do not document or depend on host-installed PHP/Composer.

### V. Classic ↔ worker compatibility

Application code must be safe in **worker mode** (resettable cross-request state; per-request stateful services implement `ResetInterface` when needed).
See `docs/frankenphp-coding.md`. Default coding target: `FRANKENPHP_MODE=worker` and `FRANKENPHP_RESET_KERNEL=false`.

### VI. Efficient ingest

Envelope endpoints MUST authenticate and acknowledge quickly, then process via Messenger. Heavy grouping, persistence, and analytics run asynchronously.

### VII. English-only documentation and code comments (NON-NEGOTIABLE)

All project prose MUST be written in **English** (docs, specs, PHPDoc, Twig UI copy, `lang="en"`).

### VIII. Tests required per feature

Every spec that changes behavior MUST ship PHPUnit coverage (unit and/or functional) matching its acceptance scenarios. CI must stay green.

## Technical constraints

- Entrypoint: `.docker/frankenphp/docker-entrypoint.sh` maps `FRANKENPHP_MODE` → `FRANKENPHP_CONFIG`.
- Document root: `/app/public`.
- Secrets: never in git. Version **only** `.env.dist`.
- Ingest auth: Envelope-compatible (`X-Sentry-Auth` / query / envelope `dsn`) mapped to project API keys.
- Primary ingest path: `POST /api/{project_id}/envelope/`.
- The Symfony client bundle (`nowo-tech/beacon-bundle`) lives in a **separate repository** (out of scope here); configure via `BEACON_DSN`.
- Native mobile shells consume `/config/ios_v1.json` and `/config/android_v1.json` (Hotwire Native); see `docs/native-mobile.md`.

## Development workflow (SDD)

1. `/speckit-constitution` — update principles for structural changes
2. `/speckit-specify` — what & why
3. `/speckit-clarify` — (optional)
4. `/speckit-plan` — how
5. `/speckit-tasks` — actionable tasks
6. `/speckit-implement` — implement per tasks
7. `/speckit-converge` — close gaps

Per-feature artifacts: `specs/NNN-name/{spec,plan,tasks}.md`.

## Governance

- This constitution overrides ad-hoc habits and agent prompts.
- Every significant PR/change must map to a spec under `specs/`.
- Amendments: edit this file, bump **Version**, update **Last Amended**.

**Version**: 1.1.1 | **Ratified**: 2026-07-19 | **Last Amended**: 2026-07-20
