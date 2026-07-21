# Tasks: Client Tags and Scrubbing

**Feature**: 023-client-tags-scrubbing  
**Primary**: BeaconBundle · **Companion**: symfony-beacon

## Bundle (primary)

- [x] T001 `Scope` with merge/limits + `kernel.reset`
- [x] T002 `BeaconClientInterface` tags API + wire into `EnvelopeBuilder`
- [x] T003 `before_send` config (service id) + mutate/drop/fail-soft
- [x] T004 Unit tests (Scope, tags on envelope, before_send mutate/drop/throw)
- [x] T005 Bundle CONFIGURATION / USAGE / README / CHANGELOG

## Beacon (companion)

- [x] T006 Event Tags panel: client tags vs system tags + hint
- [x] T007 EVENT-CONTEXT.md + DSN.md cross-links to Bundle tags/before_send
- [x] T008 plan.md / tasks.md + CHANGELOG Unreleased bullet
