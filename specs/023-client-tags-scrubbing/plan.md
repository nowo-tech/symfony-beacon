# Implementation Plan: Client Tags and Scrubbing

**Branch**: `023-client-tags-scrubbing`  
**Date**: 2026-07-21  
**Status**: Bundle-primary (companion docs/UI in Beacon)

## Summary

Public tags API and `before_send` scrubbing live in **nowo-tech/beacon-bundle**. Symfony Beacon remains the viewer: ingest existing `payload.tags`, improve Tags panel clarity, and document cross-links.

## Ownership

| Area | Repo |
|------|------|
| `Scope`, `setTag`/`setTags`, envelope `tags` | BeaconBundle |
| `before_send` config + client hook | BeaconBundle |
| Unit tests + CONFIGURATION/USAGE/CHANGELOG | BeaconBundle |
| Event Tags UI (client vs system) | symfony-beacon |
| EVENT-CONTEXT / DSN cross-links | symfony-beacon |

## Technical approach (Bundle)

- Request-scoped `Scope` merges tags (max 32; key/value length limits; scalar coercion).
- `EnvelopeBuilder` attaches `tags` on events and transactions.
- `before_send`: service id → invokable `(array): ?array`; `null` drops send; exceptions drop (fail soft).

## Companion (Beacon)

- Twig Tags panel: highlight `payload.tags` as **Client tags**; keep system fields separate.
- Docs: EVENT-CONTEXT.md + DSN.md capability table.

## Out of scope

- Server-side PII scrubbing policies
- Tag search filters (may follow issue-search work)
