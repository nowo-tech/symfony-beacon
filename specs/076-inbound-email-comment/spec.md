# Feature Specification: Inbound email → issue comment

**Feature Branch**: `076-inbound-email-comment`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.28  
**Issue**: [#42](https://github.com/nowo-tech/symfony-beacon/issues/42)

**Input**: Let members reply to Beacon mention/assign emails so the reply becomes an issue comment.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Opt-in via `BEACON_INBOUND_EMAIL_ENABLED` + domain + webhook secret |
| FR-002 | Outbound personal mail sets `Reply-To: reply+{token}@domain` |
| FR-003 | `POST /hooks/email/inbound` with `X-Beacon-Inbound-Secret` |
| FR-004 | Resolve author by From email + triage; strip quotes; create comment via `IssueCommentCreator` |
| FR-005 | Idempotent on Message-ID (`inbound_email_message`) |

## Out of Scope

IMAP, attachments, HTML MIME, Postmark/SES native adapters

## Privacy

Inbound stores comment body + associates sender email to a User. Operators should disclose this in privacy/terms.
