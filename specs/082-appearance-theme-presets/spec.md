# Feature Specification: Appearance theme presets

**Feature Branch**: `082-appearance-theme-presets`  
**Created**: 2026-08-05  
**Status**: Done (as-built)  

**Input**: Give `ROLE_ADMIN` named light and dark theme presets on Administration → Appearance that overwrite site color fields when applied; keep light and dark selections independent; tab the settings UI (Themes / Brand / Layout / Colors); add layout knobs (corner radius, border strength, pinned legal footer); export/import the new fields via instance config.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Named theme presets (Priority: P1)

As an instance admin, I open **Themes** and see light presets (`beacon`, `ocean`, `slate`, `sandstone`) and dark presets (`midnight`, `obsidian`, `aurora`, `ember`). Choosing a card applies that palette and overwrites the matching light or dark color fields.

**Independent Test**: `GET /settings/appearance/themes` as admin; click `ocean` then `midnight`; CSS variables and `aria-pressed` reflect both selections.

**Acceptance Scenarios**:

1. **Given** `ROLE_ADMIN`, **When** I open `/settings/appearance`, **Then** I am redirected to `/settings/appearance/themes` (no light/dark sub-route).
2. **Given** Themes section, **When** I apply a light preset, **Then** only light color columns change and `theme_id` stores that id; dark selection stays.
3. **Given** Themes section, **When** I apply a dark preset, **Then** only dark color columns change and `theme_id_dark` stores that id; light selection stays.
4. **Given** Colors edited manually away from a preset, **When** Themes reloads, **Then** the mismatched mode shows as `custom` (warn copy when either mode is custom).

### User Story 2 - Tabbed appearance settings (Priority: P1)

As an admin, Appearance is split into **Themes**, **Brand**, **Layout**, and **Colors** routes. Colors has subtabs Accents / Status / Surfaces. Save and Restore use shared `common.save` / `common.restore` labels.

**Acceptance**: Routes `/settings/appearance/{themes|brand|layout|colors}` and `/settings/appearance/colors/{accents|status|surfaces}`; `[data-testid="appearance-tabs"]` present; Themes has no `[data-testid="appearance-subtabs"]`.

### User Story 3 - Layout knobs (Priority: P2)

As an admin, **Layout** lets me set corner style (`sharp` / `soft` / `rounded`), border strength (`subtle` / `medium` / `strong`), and optional fixed legal footer.

**Acceptance**: Values persist on `site_appearance`; CSS variables / classes reflect corner and border; footer flag pins the legal footer when enabled.

### User Story 4 - Instance config portability (Priority: P2)

As an admin, instance config export/import includes `theme_id`, `theme_id_dark`, `corner_style`, `border_strength`, and `footer_fixed`. Named theme ids in import apply presets (same overwrite semantics as the UI).

**Acceptance**: Export JSON contains the keys; import with a known preset id rewrites colors for that mode.

### User Story 5 - Functional coverage (Priority: P1)

Automated tests cover redirect, apply light+dark on one Themes page, form sections, and preset catalogue helpers.

**Acceptance**: `tests/Functional/Shared/AppearanceSettingsTest.php`, `tests/Unit/Shared/AppearanceThemePresetsTest.php`, `tests/Unit/Shared/AppearanceCornerStylesTest.php`, `tests/Unit/Shared/AppearanceBorderStylesTest.php`.

## Requirements

- **FR-001**: Catalogue `AppearanceThemePresets` with light ids `beacon|ocean|slate|sandstone` and dark ids `midnight|obsidian|aurora|ember`; `custom` is match-only (not a clickable apply id).
- **FR-002**: `AppearanceThemePresets::apply()` overwrites only the fields for the preset’s mode (light vs dark).
- **FR-003**: Persist `theme_id` (light) and `theme_id_dark` (dark) on `site_appearance` (`Version20260805120000`, `Version20260805160000`; dark migration moves former dark ids out of `theme_id`).
- **FR-004**: Themes UI lists both modes on one page; no Themes light/dark subtabs.
- **FR-005**: Sections Themes / Brand / Layout / Colors via `AppearanceSettingsSection`; Colors subtabs Accents / Status / Surfaces via `AppearanceSettingsSubtab`.
- **FR-006**: Layout columns `corner_style`, `border_strength`, `footer_fixed` (`Version20260805130000`–`…150000`).
- **FR-007**: Instance config portability reads/writes the new keys; preset ids call `apply()`.
- **FR-008**: English UI + locale catalogues (de/en/es/fr/it/nl/pt) for tabs, themes, layout, custom warn.
- **FR-009**: CSRF token `appearance_theme` on theme apply; form CSRF on Save/Restore sections.

## Key Entities

- **SiteAppearance**: Singleton admin look & feel row; color fields + `themeId` / `themeIdDark` + layout knobs.
- **AppearanceThemePresets**: Static catalogue + apply/match helpers.
- **AppearanceSettingsSection** / **AppearanceSettingsSubtab**: Route slugs for the tabbed UI.

## Success Criteria

- **SC-001**: Admin can set independent light and dark named themes without visiting separate sub-routes.
- **SC-002**: Applying a preset visibly changes CSS variables for that mode only.
- **SC-003**: Manual color edits mark the affected mode as `custom` on Themes.
- **SC-004**: Migrations through `Version20260805160000` apply cleanly; Appearance settings and instance config round-trip the new fields.
- **SC-005**: Appearance functional/unit tests pass under `make test`.

## Related

- `044-instance-config-export` — Portability allowlist extended here.
- `078-form-save-restore-actions` — Save / Restore label convention on appearance forms.
- `077-form-type-field-loop` — Color field loop on Colors sections.
- Phase **6.6b** (v0.15.0) — Base warn/paper/ink/surface palette this feature builds on.

## Out of scope (v1)

- Per-user named theme catalogues (account display still only prefers light/dark/system).
- Uploading custom theme JSON packs.
- Live preview without Save for Brand/Layout/Colors (Themes apply is immediate POST).
- `plan.md` / contracts artifacts (as-built documented in this spec + tasks only).
