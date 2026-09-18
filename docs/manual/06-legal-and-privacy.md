# Legal and privacy

Public legal pages ship as English UI copy (`lang="en"`) with room for operator-specific text. Cookie consent uses **`nowo-tech/cookie-consent-bundle`**. Do not add non-essential cookies or third-party scripts without consent UX.

Deep dive: [LEGAL-AND-COOKIES.md](../product/LEGAL-AND-COOKIES.md).

These pages are linked from AuthKit footers (and related public shells) so guests can review obligations before and after sign-in.

## Legal notice

**Route:** legal notice page (locale-prefixed under `/{locale}/legal/…` when not on the default locale path set)

**What it is.** Operator / publisher identification and legal notices required for the self-hosted SaaS surface.

**What it contributes.** Editable operator / publisher identification. Default seed uses placeholders (no nowo.tech identity); the deploying organisation must publish its own identification before production.

![Legal notice](images/legal-notice.png)

## Privacy policy

**What it is.** How the instance processes personal data (accounts, logs, optional consent records).

**What it contributes.** GDPR-oriented disclosure for sign-up, auth, and admin tooling. Complements signed-in [Account → Privacy](03-account.md#privacy) export / anonymize tools.

![Privacy policy](images/legal-privacy.png)

## Terms of use

**What it is.** Acceptable-use and service terms for people using this Beacon instance.

**What it contributes.** Sets expectations for operators and members (abuse, availability, responsibilities) without embedding legal text in product features.

![Terms of use](images/legal-terms.png)

## Cookie policy

**What it is.** Explains cookies and similar storage used by the UI (essential vs optional categories).

**What it contributes.** Pairs with the consent modal so users understand categories before accepting optional cookies. Configure categories under [Administration → Cookie consent](05-admin-and-ops.md#cookie-consent).

![Cookie policy](images/legal-cookies.png)

On AuthKit and related public routes, the consent modal may open automatically. Screenshots in this manual dismiss it for clarity; keep consent enabled whenever analytics or optional cookies are configured.

## Account privacy tools

Signed-in users reach export / anonymize entry points from [Account → Privacy](03-account.md#privacy). Those tools cover account-scoped data; project ingest events and issues remain with the project until retention purge.
