# Security Policy

## Supported versions

Security fixes are applied to the **latest released tag** on [`main`](https://github.com/nowo-tech/symfony-beacon) and, when practical, to the previous minor release still documented in [UPGRADING.md](docs/UPGRADING.md).

| Version | Supported |
| --- | --- |
| Latest release on `main` | Yes |
| Older tags | Best-effort only; please upgrade |

Self-hosted operators should keep dependencies and Beacon itself up to date (`composer install`, migrations, and the [production guide](docs/PRODUCTION.md)).

## Reporting a vulnerability

**Do not** open a public GitHub issue for security problems (credential leaks, auth bypass, RCE, XSS that affects other tenants, etc.).

Prefer one of these private channels:

1. **GitHub Security Advisories** (preferred): [Report a vulnerability](https://github.com/nowo-tech/symfony-beacon/security/advisories/new)
2. **Email**: [hectorfranco@nowo.tech](mailto:hectorfranco@nowo.tech) with subject `symfony-beacon security`

Please include:

- Affected version / commit (or Docker image tag)
- Description of the issue and impact
- Steps to reproduce (PoC if available)
- Whether you plan a coordinated disclosure date

You should receive an acknowledgement within a few business days. We will work with you on a fix and credit (if desired) in the advisory / changelog.

## Scope notes

- This repository is the **self-hosted Beacon server**. The separate Symfony client is [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle).
- Misconfiguration of operator secrets (`.env`, Halite keys under `var/secrets/`, API keys) is an operational risk; see [PRODUCTION.md](docs/PRODUCTION.md) and [LEGAL-AND-COOKIES.md](docs/LEGAL-AND-COOKIES.md).
- Feature requests and non-security bugs belong in [GitHub Issues](https://github.com/nowo-tech/symfony-beacon/issues) using the templates.

## Safe harbour

We appreciate good-faith research. Avoid destructive testing against installations you do not operate, and do not exfiltrate personal data beyond what is needed to demonstrate the issue.
