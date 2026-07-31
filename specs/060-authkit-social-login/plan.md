# Plan: 060-authkit-social-login

**Branch**: `060-authkit-social-login` | **Date**: 2026-07-30 | **Spec**: [spec.md](./spec.md)

## Summary

Wire AuthKit social OAuth into Beacon: Packagist AuthKit 1.9+, profile config, Doctrine tables, public routes, login Twig buttons, env-based credential seeder. Default `create_user_if_missing: false` for first-user-only admin safety.

## Implementation

1. Composer AuthKit ≥ 1.9.1 (Packagist).
2. `nowo_auth_kit` `social_login` + route names; security access_control.
3. Migration MDK for AuthKit social tables.
4. Login override + locale/password-policy route lists.
5. `app:seed-social-login` from `AUTH_KIT_SOCIAL_*` env.
