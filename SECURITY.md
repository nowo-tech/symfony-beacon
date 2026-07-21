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

## Magic login and share links (`026`)

| Mechanism | Defaults | Notes |
| --- | --- | --- |
| Magic login (`login_link`) | Lifetime **600s**, **max_uses: 1**, used-link cache `cache.app` | Request rate limit 5 / 15 minutes per IP; disabled accounts rejected. **Requires** an encrypted non-null Mailer DSN under Administration → Mailer; otherwise `/login/magic` redirects to password login and the link is hidden. |
| Share links | Max **30 days**; SHA-256 hashed token | Grants session viewer access only; never embeds Envelope API secrets |

Operators should keep the **Mailer DSN** (Administration → Mailer, encrypted at rest) and `kernel.secret` production-grade; rotating the secret invalidates outstanding magic links. Env `MAILER_DSN` is only a fallback for other outbound mail when no database DSN is configured — it does **not** enable magic-link sign-in.

## Safe harbour

We appreciate good-faith research. Avoid destructive testing against installations you do not operate, and do not exfiltrate personal data beyond what is needed to demonstrate the issue.
