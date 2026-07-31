# Plan: 059-ai-issue-export

**Branch**: `059-ai-issue-export` | **Date**: 2026-07-29 | **Spec**: [spec.md](./spec.md)

## Summary

`AiIssueExportFormatter` builds canonical array + Markdown/JSON. Issue routes `export/ai.md` and `export/ai.json`. Issue show UI: Copy for AI + downloads. Scrub sensitive headers. Docs `docs/product/AI-EXPORT.md`.

## Implementation

1. Formatter service + scrub helpers.
2. Controller actions (or IssueController methods) with `requireIssueRead`.
3. Twig buttons + clipboard.
4. Unit + functional tests; CHANGELOG.
