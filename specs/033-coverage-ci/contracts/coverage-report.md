# Contract: Coverage report & soft gate

## Local command

```bash
make test-coverage
# equivalent (in php container):
# XDEBUG_MODE=coverage vendor/bin/phpunit \
#   --coverage-text \
#   --coverage-clover var/coverage/clover.xml \
#   --coverage-html var/coverage-html
```

Optional soft gate:

```bash
COVERAGE_MIN=40 make test-coverage
# or after a run:
COVERAGE_MIN=40 .scripts/check-coverage-threshold.sh var/coverage/clover.xml
```

## CI job

- Name: `coverage` (PHP 8.5, MySQL service matching `qa`)
- Driver: PCOV (`coverage: pcov`)
- Outputs: `var/coverage/clover.xml`, `var/coverage-html/`
- Upload: `actions/upload-artifact` (clover + HTML)
- Soft gate: same script; `COVERAGE_MIN` workflow env default empty

## Failure modes

| Condition | Exit | Notes |
|-----------|------|--------|
| PHPUnit failures | Fail job | Not soft |
| Clover file missing / unreadable | Fail check script | Documented generation failure |
| `COVERAGE_MIN` unset or empty | Pass (informational) | Default |
| Coverage % &lt; `COVERAGE_MIN` | Fail with clear message | Soft threshold |
| Coverage % ≥ `COVERAGE_MIN` | Pass | — |

Never require 100% coverage.
