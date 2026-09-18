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

DNS pin and `max_redirects: 0` shipped (`OutboundUrlGuard` + `DeliverNotificationHandler`). Literal and obfuscated cloud metadata stay blocked even when Ops allows private notification URLs: `169.254.0.0/16`, `fe80::/10`, Alibaba `100.100.100.200`, IPv4-mapped forms of those addresses, and decimal / hex 32-bit hosts (`2852039166`, `0xa9fea9fe`). Hostnames `metadata` and `metadata.google.internal` stay blocked. The private-URL flag still defaults to off and still allows LAN literals such as `127.0.0.1`.
