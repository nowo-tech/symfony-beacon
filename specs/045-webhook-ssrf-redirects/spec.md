# Feature Specification: Webhook SSRF via HTTP redirects

**Feature Branch**: `045-webhook-ssrf-redirects`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: Close SSRF where `OutboundUrlGuard` validates the initial URL but HttpClient follows redirects to private/metadata hosts.

## User Scenarios & Testing

### User Story 1 - No redirect following on notification delivery (Priority: P1)

As an operator, outbound notification HTTP clients must not follow redirects so a 302 cannot bypass the SSRF allowlist.

**Acceptance Scenarios**:

1. **Given** a destination URL that returns `302` to a private address, **When** delivery runs, **Then** the client does not follow the redirect (`max_redirects: 0`) and the attempt fails safely.
2. **Given** a direct HTTPS destination that passes `OutboundUrlGuard`, **When** delivery runs, **Then** the payload is sent without redirect following.

## Requirements

- `DeliverNotificationHandler` (and any equivalent outbound HTTP) sets `'max_redirects' => 0`.
- Document that destinations must use the final URL (see UPGRADING 0.12.1→0.12.2).

## Residual risk

DNS rebinding / TOCTOU after resolve remains **Planned** (extends this spec).
