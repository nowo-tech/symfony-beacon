# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-07-19

### Added

- Initial **symfony-beacon** server (forked from [symfony-frankenphp-boilerplate](https://github.com/nowo-tech/symfony-frankenphp-boilerplate))
- Modular Symfony modules: Identity, Project, Ingest, Issues, Performance, Analytics
- Envelope-compatible ingest (`POST /api/{project_id}/envelope/`) + Messenger async pipeline
- Dashboard with Tailwind (projects, issues, performance/N+1, analytics)
- Project API keys / DSN, memberships (`owner` / `admin` / `member`)
- Demo seed command (`app:seed-demo`) and PHPUnit coverage for parsers, ingest, dashboard access
- Spec-Driven Development layout (`specs/`, constitution, Spec Kit skills)

[Unreleased]: https://github.com/nowo-tech/symfony-beacon/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/nowo-tech/symfony-beacon/releases/tag/v0.1.0
