# Data Model: CI Code Coverage Report

No persistent application entities. Coverage is ephemeral CI/local output.

| Artifact | Path | Lifetime |
|----------|------|----------|
| Clover XML | `var/coverage/clover.xml` | Local `/var/` (gitignored); CI upload retention |
| HTML report | `var/coverage-html/` | Same |
| JS coverage | `var/coverage-js/` | Vitest V8 (gitignored `/var/`) |
| Hard threshold | Env `COVERAGE_MIN` (percent; CI/Makefile default **100**) | Empty = informational only (local diagnosis) |
| Exclusion inventory | [docs/COVERAGE.md](../../../docs/COVERAGE.md) | Documents PHPUnit `<source><exclude>` + Vitest `include` |
