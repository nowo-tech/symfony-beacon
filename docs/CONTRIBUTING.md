# Contributing

1. Follow Spec-Driven Development (see `.specify/memory/constitution.md`).
2. Open or update a feature under `specs/NNN-name/` before large changes.
3. Keep application code FrankenPHP **worker-safe** (`docs/frankenphp-coding.md`).
4. Add PHPUnit coverage for behavior changes.
5. Run `make test` (and ideally `make qa`) before opening a PR.
6. English only for docs, specs, PHPDoc, and UI copy.

The client Symfony bundle is **out of scope** for this repository.
