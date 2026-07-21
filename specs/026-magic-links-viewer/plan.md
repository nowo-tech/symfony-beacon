# Implementation Plan: Magic Links and Viewer Role

**Branch**: `026-magic-links-viewer`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Summary

Add read-only **`ProjectRole::Viewer`**, passwordless **magic login** via Symfony Security `login_link` (AuthKit remains form_login; no parallel credential store), and optional **signed share links** (project or issue) that grant session-scoped viewer access. Prefer kits; SSO stays Later.

## Technical Context

| Area | Decision |
|------|----------|
| Viewer rank | `viewer=0 < member=1 < admin=2 < owner=3` |
| Triage gate | Issue POST + saved-view mutations require `ProjectRole::Member` (`canTriageIssues`) |
| Groups | May assign viewer / member / admin (still never owner) |
| Magic login | Symfony `login_link` (lifetime 10m, max_uses 1, cache for used links) + Mailer via encrypted instance settings (`034`); request UI at `/login/magic` |
| Throttle | Dedicated rate limiter on magic-link request by client IP |
| Share links | `project_share_link` entity; hashed token; session grant `_beacon_share_access`; no API secrets |
| Audit | `UserActionType` magic/share cases |

## Constitution Check

- Prefer AuthKit / Security login-link (FR-008)
- English docs / UI; remind legal = operational email only (no new cookies)
- Spec-first `026`

## Project Structure

```text
src/Shared/ProjectRole.php
src/Project/Service/ProjectAccessService.php
src/Project/Service/ProjectMembershipManager.php
src/Identity/Controller/MagicLoginController.php
src/Project/Entity/ProjectShareLink.php
src/Project/Controller/ProjectShareLinkController.php
src/Project/Service/ProjectShareLinkManager.php
config/packages/security.yaml
config/packages/rate_limiter.yaml
migrations/Version20260721180000.php
```
