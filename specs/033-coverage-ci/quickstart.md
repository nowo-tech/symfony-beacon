# Quickstart: 033-coverage-ci

## Local

1. `make up` (stack running).
2. `make test-coverage` — prints text summary; writes `var/coverage/clover.xml` and `var/coverage-html/`; defaults to `COVERAGE_MIN=100`.
3. Open `var/coverage-html/index.html` in a browser.
4. Diagnosis only: `COVERAGE_MIN=0 make test-coverage` (skips the hard fail while inspecting gaps).
5. Frontend: `make test-unit-js-coverage` → `var/coverage-js/` (Vitest 100% on the includable set).

## CI

1. Push a branch / open a PR.
2. Confirm job **Coverage (PHP 8.5)** runs after checkout + composer + MySQL prepare.
3. Download the coverage artifact from the Actions run.
4. Confirm workflow `env.COVERAGE_MIN` is `"100"`. The job fails if includable statement coverage drops below that floor.

## Docs check

- [docs/CONTRIBUTING.md](../../docs/CONTRIBUTING.md) documents `make test-coverage` and `COVERAGE_MIN=100`.
- [docs/COVERAGE.md](../../docs/COVERAGE.md) lists PHP exclusions and the TypeScript includable set.
