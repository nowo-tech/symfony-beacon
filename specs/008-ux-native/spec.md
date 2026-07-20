# Feature Specification: UX Native (Hotwire Native mobile shell)

**Feature Branch**: `008-ux-native`  
**Created**: 2026-07-20  
**Status**: Completed  

**Input**: User description: "Pass the project to Symfony UX Native so we can have a mobile application."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Serve native client configuration (Priority: P1)

As a mobile engineer, I can fetch JSON path/settings configuration from Beacon so a Hotwire Native iOS or Android shell knows how to open login, dashboard, and API routes.

**Why this priority**: Without public config endpoints, native shells cannot be pointed at an arbitrary Beacon deployment (open-source self-host on any domain/port).

**Independent Test**: Request `/config/ios_v1.json` and `/config/android_v1.json` anonymously and verify valid JSON with path rules.

**Acceptance Scenarios**:

1. **Given** the Beacon app is running, **When** an anonymous client GETs `/config/ios_v1.json`, **Then** the response is successful JSON including `rules`.
2. **Given** the Beacon app is running, **When** an anonymous client GETs `/config/android_v1.json`, **Then** the response is successful JSON including `rules`.
3. **Given** production deployment, **When** operators run the documented dump command, **Then** static config files can be written under `public/` for direct web-server serving.

---

### User Story 2 - Native-aware web UI (Priority: P1)

As a user opening Beacon inside a Hotwire Native WebView, I see a UI adapted for the native shell (no duplicate browser/PWA chrome) while keeping login and the authenticated app usable.

**Why this priority**: Native shells already provide navigation/branding; duplicating PWA install UI and dense web chrome hurts mobile UX.

**Independent Test**: Request a public page (e.g. login) with a Hotwire Native User-Agent and assert native layout markers; request without that UA and assert PWA hooks remain.

**Acceptance Scenarios**:

1. **Given** a request whose User-Agent contains `Hotwire Native`, **When** the login (or app) layout renders, **Then** the document is marked as native and PWA install affordances are omitted.
2. **Given** a normal browser User-Agent, **When** the same page renders, **Then** PWA head/install affordances remain available.
3. **Given** a native session, **When** the user authenticates with existing session login, **Then** they reach the dashboard without requiring a separate mobile API.

---

### User Story 3 - In-app navigation suitable for WebView (Priority: P2)

As a native-shell user, navigating between Beacon pages feels like an app (no full white flash / full reload where avoidable).

**Why this priority**: Hotwire Native relies on Turbo Drive for fluid WebView navigation.

**Independent Test**: Confirm Turbo is part of the front-end asset pipeline and pages use the shared layout that loads those assets.

**Acceptance Scenarios**:

1. **Given** the front-end build, **When** assets are built, **Then** Turbo is included for Drive-style navigation.
2. **Given** authenticated pages, **When** theme/sidebar UI is used after Turbo navigation, **Then** controls remain functional (no broken listeners).

---

### User Story 4 - Document how to ship store apps (Priority: P2)

As an operator or contributor, I can follow project documentation to point iOS/Android Hotwire Native clients at my Beacon base URL (any domain/subdomain/port).

**Why this priority**: Server support alone does not produce App Store / Play binaries; operators need a clear guide.

**Independent Test**: Open `docs/NATIVE-MOBILE.md` and verify it describes config URLs, UA behaviour, and client scaffolding pointers.

**Acceptance Scenarios**:

1. **Given** the repository docs, **When** a reader follows the native mobile guide, **Then** they know which config URLs to load and that the client base URL is deployment-specific.
2. **Given** legal/store distribution needs, **When** the guide is read, **Then** privacy/terms and consent reminders are mentioned for store releases.

---

### Edge Cases

- Envelope ingest URLs must not be treated as navigable WebView pages (API-only).
- Self-signed local HTTPS may require device trust configuration outside Beacon.
- Empty or missing native User-Agent must keep full browser/PWA behaviour.
- Locale-prefixed auth routes (`/en/…`, `/es/…`) must remain usable in modal or default native contexts.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST detect Hotwire Native clients via User-Agent and expose that signal to templates/controllers.
- **FR-002**: System MUST publish versioned native configuration documents for iOS and Android at stable public paths.
- **FR-003**: Native configuration MUST include path rules covering default app browsing, auth routes, and non-navigation for Envelope ingest paths.
- **FR-004**: When a request is native, the UI MUST omit PWA install prompts/links and reduce redundant web chrome that the native shell already provides.
- **FR-005**: When a request is not native, existing PWA behaviour MUST continue to work.
- **FR-006**: The product MUST support Turbo-based front-end navigation compatible with Hotwire Native WebViews.
- **FR-007**: Session-based authentication (existing login) MUST remain the supported way to use the dashboard inside the native shell (no separate mobile-only auth required for this feature).
- **FR-008**: Documentation MUST describe how to point a Hotwire Native client at any self-hosted Beacon base URL (host/port).
- **FR-009**: Automated tests MUST cover public native config endpoints and native User-Agent layout behaviour.

### Key Entities

- **Native configuration document**: Versioned settings + ordered path rules consumed by Hotwire Native clients (built by `App\Native\AppNativeConfiguration`).
- **Native request**: An HTTP request identified as coming from a Hotwire Native shell (`ux_is_native()`).
- **Theme bridge**: Stimulus `beacon-theme-bridge` keeps native chrome in sync with app theme after Turbo navigations.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Anonymous GET of iOS and Android config paths returns usable JSON in under 2 seconds on a healthy local stack.
- **SC-002**: With a Hotwire Native User-Agent, login HTML includes the native shell marker and does not present PWA install chrome.
- **SC-003**: A contributor can locate end-to-end native setup steps in project docs without reading source code.
- **SC-004**: PHPUnit coverage for native config + native UA layout stays green in CI.

## Assumptions

- Symfony UX Native remains the integration point for Hotwire Native detection/config (component may evolve; behaviour above is the contract).
- Shipping App Store / Play Store binaries is **out of scope** for this repo; only server support + docs are in scope.
- PWA (`nowo-tech/pwa-bundle`) continues to serve browser “install to home screen”; native store apps replace that path for mobile users who use the shell.
- Legal pages and cookie consent may be required before public store distribution but are tracked as operator obligations, not blockers for server-side native support.

## Out of Scope

- Separate JSON REST API + token auth for a fully native UI rewrite (React Native / Flutter).
- Maintaining first-party Xcode / Android Studio projects inside this repository (may be added later).
- Push notifications, NFC, camera bridges beyond a minimal theme bridge hook.
