# Tasks: issue-fulltext

**Input**: [spec.md](./spec.md)  
**Plan**: [plan.md](./plan.md)

## Phase 0: Spec (done)

- [x] T000 Draft `spec.md`

## Phase 1: Plan (done)

- [x] T001 `/speckit-plan` → `plan.md`
- [x] T002 Expand `tasks.md`

## Phase 2: Implementation (done)

- [x] T003 Entity FULLTEXT index flag on `Issue`
- [x] T004 Migration MySQL-only FULLTEXT
- [x] T005 `IssueRepository` MATCH / LIKE dual path
- [x] T006 Functional/repository test for token match (SQLite LIKE)
- [x] T007 UPGRADING + ROADMAP + CHANGELOG
