# Implementation Plan: SQL / database error context on issue detail

**Branch**: `107-sql-error-context` | **Date**: 2026-08-26 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/107-sql-error-context/spec.md`

## Summary

Issue/event UI derives **Query facts** (SQLSTATE, vendor code, SQL, bindings) from the stored Envelope payload and shows a first-class Query panel. Stack default-opens the innermost **in-app** frame. Culprit storage grows to 255 characters. BeaconBundle **1.8.0** attaches structured `contexts.db` on database exceptions without requiring Doctrine span instrumentation.

## Technical Context

**Language/Version**: PHP 8.5 (Beacon), PHP 8.2–8.5 (BeaconBundle)

**Primary Dependencies**: Symfony 8.1, Twig, Doctrine ORM/DBAL, PHPUnit; sibling `nowo-tech/beacon-bundle`

**Storage**: MySQL 9.7 `issue.culprit` VARCHAR(255); `event.payload` JSON unchanged (no Query facts table)

**Testing**: PHPUnit unit + functional (`DatabaseWebTestCase`); Bundle unit + EnvelopeBuilder; no new cookies/E2E required for MVP (extend `IssueErrorSurfacesFunctionalTest`)

**Target Platform**: Twig issue/event detail (PWA); Bundle client apps

**Project Type**: Modular Symfony web app + Symfony bundle

**Performance Goals**: Derivation on a single event payload; no extra SQL at render

**Constraints**: Worker-safe; English UI/docs; Principle IX (no `env():` in parameters); Principle X (no Cursor trailers); MDK/idempotent migration; includable coverage 100%

**Scale/Scope**: One new Issues service + Twig extension + panel; one Bundle context helper; one ALTER; translations in all locales

## Constitution Check

- [x] Spec-first: `specs/107-sql-error-context/`
- [x] Canonical stack / Docker-first / worker-safe
- [x] English docs/PHPDoc/UI; tests planned
- [x] Env/config: no `env(VAR_NAME):` defaults
- [x] Prefer nowo-tech kits: N/A (telemetry UI, not auth/legal)
- [x] No Cursor / agent attribution

Post-design: same gates hold. Bundle work lives in the sibling repo (constitution already names BeaconBundle as separate). Culprit ALTER is host schema, not a kit.

## Project Structure

### Documentation (this feature)

```text
specs/107-sql-error-context/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/query-facts.md
└── tasks.md
```

### Source Code

```text
# Beacon
src/Issues/Dto/QueryFacts.php
src/Issues/Service/QueryFactsExtractor.php
src/Issues/Service/IssueStackPresenter.php
src/Issues/Twig/IssueEventTwigExtension.php
src/Issues/IssuePanelIds.php
src/Issues/Entity/Issue.php
src/Issues/Export/AiIssueExportFormatter.php
src/Issues/Service/IssueSampleSeeder.php
templates/issue/_event_payload.html.twig
templates/issue/show.html.twig
migrations/Version20260826120000.php
tests/Unit/Issues/Service/QueryFactsExtractorTest.php
tests/Unit/Issues/Service/IssueStackPresenterTest.php
tests/Functional/Issues/IssueErrorSurfacesFunctionalTest.php

# BeaconBundle (sibling)
src/Context/DatabaseExceptionContext.php
src/Envelope/EnvelopeBuilder.php
tests/Unit/Context/DatabaseExceptionContextTest.php
tests/Unit/Envelope/EnvelopeBuilderTest.php
docs/CHANGELOG.md  → 1.8.0
```

**Structure Decision**: Query derivation stays in `Issues` (read path). Twig extension in `Issues/Twig` (not Shared). Bundle context helper has no Doctrine hard dependency (`method_exists` / `PDOException` only).

## Complexity Tracking

None. No constitution violations.
