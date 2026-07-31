# Quickstart: 033-coverage-ci

## Local

1. `make up` (stack running).
2. `make test-coverage` — prints text summary; writes `var/coverage/clover.xml` and `var/coverage-html/`.
3. Open `var/coverage-html/index.html` in a browser.
4. Optional: `COVERAGE_MIN=1 .scripts/check-coverage-threshold.sh var/coverage/clover.xml` (should pass once a baseline exists; use a high value only to verify fail messaging).

## CI

1. Push a branch / open a PR.
2. Confirm job **Coverage (PHP 8.5)** runs after checkout + composer + MySQL prepare.
3. Download the coverage artifact from the Actions run.
4. Confirm `COVERAGE_MIN` is empty in the workflow (informational). To enable a soft gate later, set the workflow `env.COVERAGE_MIN` or a repository variable and re-run.

## Docs check

- [docs/CONTRIBUTING.md](../../docs/CONTRIBUTING.md) documents `make test-coverage` and the soft-gate env.
