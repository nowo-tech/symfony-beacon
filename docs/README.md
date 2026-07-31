# Documentation index

English operator and developer manuals for **symfony-beacon**.

## Start here (docs root)

| Doc | Topic |
|-----|--------|
| [INSTALL.md](INSTALL.md) | First install, seed layers, SiteBackup setup |
| [PRODUCTION.md](PRODUCTION.md) | Prod image, secrets, health, Messenger |
| [UPGRADING.md](UPGRADING.md) | Version-to-version upgrade steps |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [ROADMAP.md](ROADMAP.md) | Product phases and Later backlog |
| [RELEASE.md](RELEASE.md) | Cut a tagged release |
| [CONTRIBUTING.md](CONTRIBUTING.md) | PR / QA / SDD conventions |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Modular layout and flows |
| [API.md](API.md) | HTTP ingest, health, OpenAPI |
| [DSN.md](DSN.md) | Client DSN and auth |

## Product (`product/`)

| Doc | Topic |
|-----|--------|
| [NOTIFICATIONS.md](product/NOTIFICATIONS.md) | Slack / Teams / email / HTTP outbound + member push |
| [INBOUND-EMAIL.md](product/INBOUND-EMAIL.md) | Reply-to-email → issue comments |
| [EVENT-CONTEXT.md](product/EVENT-CONTEXT.md) | Promoted event fields, tags, spans |
| [AI-EXPORT.md](product/AI-EXPORT.md) | `beacon-ai-export/v1` Copy for AI |
| [ROLES.md](product/ROLES.md) | Instance roles vs project membership |
| [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md) | Legal pages and cookie consent |

## Operations (`ops/`)

| Doc | Topic |
|-----|--------|
| [FRANKENPHP-CODING.md](ops/FRANKENPHP-CODING.md) | Classic vs worker modes, ResetInterface |
| [MERCURE.md](ops/MERCURE.md) | Live issue toasts (hub, JWT, Compose) |
| [MAILPIT.md](ops/MAILPIT.md) | Local SMTP catcher for development |

## Development (`dev/`)

| Doc | Topic |
|-----|--------|
| [DATABASE.md](dev/DATABASE.md) | Schema / Mermaid ER |
| [ADDING-LOCALES.md](dev/ADDING-LOCALES.md) | Enable a UI language |
| [NATIVE-MOBILE.md](dev/NATIVE-MOBILE.md) | PWA note (Hotwire Native removed) |
| [FUNDING.md](dev/FUNDING.md) | Sponsors / support |

## Also

- Feature specs: [`specs/`](../specs/)
- Security policy: [`SECURITY.md`](../SECURITY.md)
- Constitution: [`.specify/memory/constitution.md`](../.specify/memory/constitution.md)
