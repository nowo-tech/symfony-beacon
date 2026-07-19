# Feature Specification: Bootstrap symfony-beacon

**Feature Branch**: `001-bootstrap`  
**Created**: 2026-07-19  
**Status**: Completed  

## User Scenarios & Testing

### User Story 1 - Boot the product stack (Priority: P1)

As a developer, I can start Docker Compose and reach a Tailwind-based login page for symfony-beacon.

**Acceptance Scenarios**:

1. Given a fresh clone with `.env` from `.env.dist`, when I run `make up`, then the stack starts on ports 9081/9444.
2. Given the stack is up, when I open `/en/login` (or `/login`, which redirects), then I see the brand **symfony-beacon**.

### User Story 2 - Modular layout + security skeleton (Priority: P1)

As a developer, the codebase uses modular Symfony packages and Security is registered.

**Acceptance Scenarios**:

1. Given the repo, when I inspect `src/`, then Identity/Project/Ingest/Issues/Performance/Analytics/Shared exist.
2. Given an anonymous request to `/`, when it is handled, then it redirects to `/en/login`.
3. Given an authenticated user, when they open `/dashboard`, then the projects home loads.

## Requirements

- Constitution updated for the product mission and Envelope compatibility
- Tailwind via Vite
- Security bundle enabled
- Docs, specs, and PHPDoc in English; UI may be translated (`en` default, see `docs/CONTRIBUTING.md`)
