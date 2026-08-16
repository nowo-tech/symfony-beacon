# Contract: Coverage report & hard gate (REQ-QA-002)

## Local command

```bash
make test-coverage
# defaults to COVERAGE_MIN=100
# diagnosis only:
# COVERAGE_MIN=0 make test-coverage
```

Equivalent inside the php container:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit \
  --coverage-text \
  --coverage-clover var/coverage/clover.xml \
  --coverage-html var/coverage-html
COVERAGE_MIN=100 .scripts/check-coverage-threshold.sh var/coverage/clover.xml
```

TypeScript:

```bash
make test-unit-js-coverage
# Vitest V8 thresholds: lines/statements 100 on the includable whitelist
```

## CI job

- Name: `coverage` (PHP 8.5, MySQL service matching `qa`)
- Driver: PCOV (`coverage: pcov`)
- Outputs: `var/coverage/clover.xml`, `var/coverage-html/`
- Upload: `actions/upload-artifact` (clover + HTML)
- Hard gate: `.scripts/check-coverage-threshold.sh` with workflow `env.COVERAGE_MIN: "100"`

## Failure modes

| Condition | Exit | Notes |
|-----------|------|--------|
| PHPUnit failures | Fail job | — |
| Clover file missing / unreadable | Fail check script | Documented generation failure |
| `COVERAGE_MIN` unset or empty | Pass (informational) | Local diagnosis only; CI sets `100` |
| Coverage % &lt; `COVERAGE_MIN` | Fail with clear message | Hard floor in CI |
| Coverage % ≥ `COVERAGE_MIN` | Pass | Default floor is **100** on includable `src/` |

Exclusions and Vitest whitelist: [docs/COVERAGE.md](../../../docs/COVERAGE.md).
