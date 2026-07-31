# Data Model: CI Code Coverage Report

No persistent application entities. Coverage is ephemeral CI/local output.

| Artifact | Path | Lifetime |
|----------|------|----------|
| Clover XML | `var/coverage/clover.xml` | Local `/var/` (gitignored); CI upload retention |
| HTML report | `var/coverage-html/` | Same |
| Soft threshold | Env `COVERAGE_MIN` (percent, e.g. `40`) | Empty = informational only |
