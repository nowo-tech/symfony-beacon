# Contributing

1. Follow Spec-Driven Development (see `.specify/memory/constitution.md`).
2. Open or update a feature under `specs/NNN-name/` before large changes.
3. Prefer official [`nowo-tech/*`](https://packagist.org/packages/nowo-tech/) kits (AuthKit, UserKit, AuditKit, cookie consent, …) over reinventing auth/user/legal UX — see `.cursor/rules/nowo-tech-kits-and-legal.mdc`.
4. Keep application code FrankenPHP **worker-safe** (`docs/frankenphp-coding.md`).
5. Add PHPUnit coverage for behavior changes.
6. Frontend: TypeScript + SCSS + Tailwind 4 under `assets/` (do not put Tailwind `@apply` inside SCSS).
7. Run `make test` (and ideally `make qa`) before opening a PR.
8. English only for docs, specs, PHPDoc, and UI copy.

The client Symfony bundle is **out of scope** for this repository.
