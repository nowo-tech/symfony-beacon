# Feature Specification: Operator legal page editor

**Feature Branch**: `115-operator-legal-editor`  
**Created**: 2026-09-18  
**Status**: Implemented (unreleased; not in tag v1.29.3)  
**Roadmap**: Phase 6 follow-up after 6.66  

**Input**: Each deploying operator must change the public legal notice, privacy policy, terms, and cookie page without forking the repository. YAML/Twig placeholders alone are not enough. The operator needs a stored document per page and locale, edited in administration, with the built-in seed still shown until they save.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| L1 | Catalog | Four public pages (`notice`, `privacy`, `terms`, `cookies`) × locales `en`, `es`, `de`, `nl`, `fr`, `it`, `pt` |
| L2 | Seed | Built-in Twig bodies + translations stay the live page until a locale is saved |
| L3 | Admin | `ROLE_ADMIN` list and editor at `/admin/legal`; save and restore built-in text |
| L4 | Publish | Saved HTML wins on the public page for that slug and locale only |
| L5 | Safety | Stored HTML is sanitized; scripts and event handlers cannot be published |
| L6 | Editor chrome | Legal admin routes allow the editor’s injected styles without weakening script policy |
| L7 | Proof | Functional + unit tests, product E2E, manual chapter shots |

## Non-goals

- Counsel-approved production copy (REQ-CC-010 stays pending; seed placeholders stay generic — REQ-CC-011)
- Replacing `nowo-tech/cookie-consent-bundle` or its admin screen
- Letting every end user edit legal copy (one operator admin per deployment)
- A custom login/registration controller
- Freezing brand name, session-cookie name, or the “manage cookies” control inside the seed after save (those stay live only while the seed is shown)
- Committing a second viewport of the editor that only shows open toolbar menus

## User Scenarios & Testing

### User Story 1 - Publish one locale without a fork (Priority: P1)

As the administrator of a deployment, I replace the English legal notice with this operator’s text and visitors of that locale see it. Other locales keep the built-in seed.

**Why this priority**: This is the reason the YAML-only pages were rejected.

**Independent Test**: Functional test saves HTML for `notice`/`en`, asserts the public English page shows it, and asserts another locale still shows the seed.

**Acceptance Scenarios**:

1. **Given** no stored document, **When** a guest opens a legal page, **Then** they see the built-in seed (placeholders such as `[Operator legal name — replace]`, not nowo.tech identity).
2. **Given** `ROLE_ADMIN`, **When** I save a non-empty body for one slug and locale, **Then** only that public page changes.
3. **Given** a member without `ROLE_ADMIN`, **When** I open `/admin/legal`, **Then** I am refused.

### User Story 2 - Start from the seed and restore it (Priority: P1)

As an administrator opening a page that was never saved, the editor is already filled with the built-in text for that locale. Restore removes my override and the public page returns to the seed.

**Why this priority**: Operators must not start from a blank page, and must be able to undo a bad save.

**Independent Test**: GET the editor with no row shows seed HTML; POST restore with no row is a no-op success; POST restore after a save deletes the row and the public page shows the seed again.

**Acceptance Scenarios**:

1. **Given** no stored row, **When** I open the editor, **Then** title and body match the built-in text for that locale.
2. **Given** a stored row, **When** I restore built-in text, **Then** the row is gone and the public page uses the seed.
3. **Given** a body that is empty after tags are stripped, **When** I save, **Then** nothing is stored and I see an error.

### User Story 3 - Unsafe HTML is not published (Priority: P1)

As a visitor, I never receive script or inline event handlers that an administrator pasted into the editor.

**Why this priority**: Legal pages are public and the body is rendered as HTML.

**Independent Test**: Saving a body that is only a script is rejected (public page stays on the seed). Saving a paragraph plus a script publishes the paragraph only.

**Acceptance Scenarios**:

1. **Given** a body whose only content is a script, **When** I save, **Then** the save is rejected.
2. **Given** mixed HTML and a script, **When** I save, **Then** the public page shows the HTML and not the script.
3. **Given** a paragraph plus an iframe or an unquoted event handler, **When** I save, **Then** the public page shows the paragraph only.

### User Story 4 - Editor styles load (Priority: P2)

As an administrator, the legal editor shows its toolbar and content with styles, not unstyled native controls.

**Why this priority**: The manual and the admin screen are unusable if the editor CSS is blocked.

**Independent Test**: Unit test on the legal admin path asserts style policy allows inline styles and does not attach a nonce to that directive; a nonce would make browsers ignore inline styles. Script policy still has no `unsafe-eval`.

**Acceptance Scenarios**:

1. **Given** `/admin/legal` or a child path, **When** the response is HTML, **Then** element styles may be inline and that directive has no nonce.
2. **Given** any other path, **When** the response is production HTML, **Then** element styles stay nonce-only (debug profiler exception unchanged).

### User Story 5 - Manual shows the editor (Priority: P2)

As an operator reading the product manual, I see the legal-pages list and the English notice editor with a styled toolbar.

**Why this priority**: The screen catalog must match the admin surface.

**Independent Test**: `docs/manual/05-admin-and-ops.md` and `docs/manual/06-legal-and-privacy.md` reference `admin-legal.png` and `admin-legal-edit.png` (1440×900). `admin-legal-edit-2.png` is not part of the inventory.

**Acceptance Scenarios**:

1. **Given** the capture suite, **When** legal admin shots are written, **Then** the editor shot waits until the editor is visible and does not scroll into open toolbar menus.

### Edge Cases

- Authenticated users follow account locale, not the URL. A public check of a stored locale MUST use a cookie-less session.
- Saving does not copy the document to other locales.
- Restore of a missing row still succeeds.
- The seed’s live bits (brand name, session cookie name, cookie-settings control, privacy link to the cookie page) stop updating after that locale is saved, because the stored body is HTML.
- Cookie consent configuration remains a separate admin screen.

## Requirements

### Functional Requirements

- **FR-001**: A deployment administrator MUST edit notice, privacy, terms, and cookies per supported locale without changing repository files.
- **FR-002**: Until a locale is saved, the public page MUST keep showing the built-in seed (translated Twig), including generic operator placeholders.
- **FR-003**: The editor MUST open prefilled from that seed when no document exists for the slug and locale.
- **FR-004**: Saving MUST store title and body for that slug and locale only, and MUST record who saved it.
- **FR-005**: A body that is empty after markup is removed MUST be rejected and MUST NOT replace an existing document.
- **FR-006**: Stored and rendered HTML MUST be sanitized on save and again on public render. Scripts, inline event handlers (quoted or not), `javascript:` / `data:` / `vbscript:` URLs, and iframes MUST NOT be published. The editor kit allowlist is not sufficient alone: a permitted iframe is returned whole, including `srcdoc`.
- **FR-007**: Restore MUST delete the stored document for that slug and locale and return the public page to the seed.
- **FR-008**: Only `ROLE_ADMIN` MAY open the legal admin screens. Public legal routes stay reachable without a session.
- **FR-009**: On legal admin paths, element styles MUST allow the editor’s injected styles and MUST omit a style nonce on that directive. Other routes MUST keep the existing nonce style policy. Script execution MUST NOT gain `unsafe-eval`.
- **FR-010**: Product E2E MUST save one locale, read it as a guest, and restore the seed. The admin HTML sample MAY include the legal list and MUST NOT require the editor chrome in that sample.
- **FR-011**: The product manual MUST show the legal list and the styled English notice editor (`111`).

### Key Entities

- **Legal document**: One published page for a single slug and locale. Title, HTML body, and who created or last updated it. Absent row means “use the seed”.

## Success Criteria

### Measurable Outcomes

- **SC-001**: An administrator can replace one locale and see it on the public page in a single save, without a repository fork.
- **SC-002**: The other six locales of that page, and the other three pages, stay on the seed after that save.
- **SC-003**: A script-only body never becomes the public page.
- **SC-004**: Restore returns the public page to the seed placeholders in one action.
- **SC-005**: Functional, unit, and product E2E checks for the flows above pass; manual PNGs for the list and the editor are committed and referenced.

## Assumptions

- “Each user of the project” means each deploying operator, not each end user of that deployment.
- The rich-text control is the official editor kit (`nowo-tech/ckeditor5-editor-bundle` through FormKit), not a CDN build.
- Cookie consent remains `nowo-tech/cookie-consent-bundle`. Seed copy stays generic until counsel and the operator replace it (REQ-CC-010 / REQ-CC-011).
- Built-in Twig bodies are the seed and the editor prefill. They are not dead templates.
- v1.29.3 described YAML-only legal placeholders and does not include this editor. Do not treat that tag as this feature.

## As-built notes

- Admin: `AdminLegalDocumentController` (`admin_legal_index`, `admin_legal_edit`, `admin_legal_reset`). Reset uses a Symfony form (`csrf_token_id` `legal_document_reset`).
- Public: `LegalController` renders `legal/stored.html.twig` when the stored body has text; otherwise the existing page template includes `legal/_{slug}_body.html.twig`.
- Prefill: `LegalBuiltinHtml`.
- Storage: migration `Version20260918160000` (`legal_document`, unique slug+locale).
- Publish filter: `html_sanitizer: strict` (`nowo-tech/ckeditor5-editor-bundle` 1.4.7) plus `LegalPublishedHtml` on save and on public render. Iframes are not kept.
- CSP: `ContentSecurityPolicySubscriber::isCkeditorAdminPath` — see `053` amendment.
- Tests: `LegalDocumentAdminTest`, `LegalControllerTest`, `ContentSecurityPolicySubscriberTest`, `e2e/admin/legal-pages.spec.ts`.
- Manual: `docs/manual/images/admin-legal.png`, `admin-legal-edit.png`.

## Cross-refs

- Public locale paths: `specs/002-identity-project/`
- Cookie consent: `specs/103-cookie-consent-vite-e2e-security/`, `docs/product/LEGAL-AND-COOKIES.md`
- Application CSP: `specs/053-security-headers/`
- Manual captures: `specs/111-product-ui-manual/`
- Footer locale (unchanged): `specs/114-identity-error-docs/` FR-014
