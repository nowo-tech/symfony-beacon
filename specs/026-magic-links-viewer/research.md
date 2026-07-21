# Research: Magic Links and Viewer Role

## Magic login

**Choice**: Symfony Security `login_link` authenticator alongside existing AuthKit `form_login`.

**Rationale**: AuthKit 1.5.1 has no passwordless login API (password reset only). Symfony login links are the documented Security feature; FR-008 allows Security login-link. Lifetime 600s, `max_uses: 1`, `used_link_cache: cache.app`.

**Anti-enumeration**: Always flash the same success message whether or not the email exists / is enabled; only send mail for enabled users.

## Viewer

**Choice**: New enum case `viewer` below `member`. Issue mutations use `requireRole(..., Member)`.

**Rationale**: Today all Issue POSTs use `requireMembership`, so a viewer would inherit write access without this gate.

## Share links

**Choice**: DB row + hashed token; consume sets session `_beacon_share_access[projectUuid]` with expiry and optional issue uuid; `ProjectAccessService` merges Viewer into effective access.

**Rationale**: Avoid auto-creating permanent memberships; revocable; no Envelope secrets in URL.
