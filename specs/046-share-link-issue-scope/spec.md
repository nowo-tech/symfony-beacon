# Feature Specification: Share link issue scope

**Feature Branch**: `046-share-link-issue-scope`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: Issue-scoped share tokens must not grant project-wide viewer access.

## User Scenarios & Testing

### User Story 1 - Issue-scoped grants stay issue-scoped (Priority: P1)

As a project owner sharing a single issue, viewers with that token can open that issue (and its events) but not the project issue list or analytics.

**Acceptance Scenarios**:

1. **Given** an issue-scoped share grant, **When** the viewer opens the project issues list or analytics, **Then** access is denied.
2. **Given** an issue-scoped share grant, **When** the viewer opens that issue (and events), **Then** access is allowed via `requireIssueRead()`.
3. **Given** a project-wide share grant, **When** the viewer opens project pages allowed for viewers, **Then** access is allowed.

## Requirements

- `ProjectAccessService` distinguishes project-wide vs issue-scoped share grants.
- Issue show / event routes use issue-scoped checks.
