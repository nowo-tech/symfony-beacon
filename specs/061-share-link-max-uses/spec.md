# Spec: Share link max uses (`061`)

## Problem

Share links remain reusable until expiry or revoke. A leaked URL grants viewer access for the full TTL (up to 30 days).

## Goal

Optional **max uses** per share link, aligned with magic-login `max_uses`, without breaking existing rows.

## Requirements

1. Columns `max_uses` (nullable int; `NULL` = unlimited) and `use_count` (int, default 0).
2. `isUsable()` is false when revoked, expired, or exhausted (`max_uses !== null && use_count >= max_uses`).
3. Each successful `/share/{token}` open increments `use_count` and updates `last_used_at`.
4. Settings UI: optional max-uses; **default 1** for new links; empty field = unlimited.
5. Existing links (`max_uses` NULL) keep multi-use behaviour until expiry/revoke.
6. Docs: `SECURITY.md`, CHANGELOG, UPGRADING, DATABASE.

## Out of scope

- SiteBackup `/setup` hardening (`062`)
- Changing magic-login defaults
