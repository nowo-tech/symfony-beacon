# Database schema

Beacon persists application data with **Doctrine ORM** on **MySQL** (Compose service `database`; local data under `./.data/mysql`).

This page documents the **App\\** entity model (tables under `src/`). Kit tables from `nowo-tech/*` bundles (menus, breadcrumbs, cookie consent, login throttle, …) are owned by those packages and are omitted here.

## Conventions

| Topic | Behaviour |
|-------|-----------|
| Naming | Underscore columns (`doctrine.orm.naming_strategy.underscore`) |
| IDs | Integer auto-increment PKs unless noted |
| Public ids | Many entities also have a `uuid` string(36) for URLs |
| Soft secrets | `#[Encrypted]` (Halite): API secrets, webhook URLs, push keys, Mailer/Mercure settings |
| Singletons | `instance_settings.id = 1`, `site_appearance.id = 1` |
| Join tables | Modelled as entities (`project_membership`, `user_group_membership`, `project_group_access`) |

Source of truth: entity mappings under `src/**/Entity/` and migrations in `migrations/`.

---

## Overview (relationships)

```mermaid
erDiagram
    app_user ||--o{ password_history : has
    app_user ||--o{ project_membership : member_of
    app_user ||--o{ user_group_membership : in
    app_user ||--o{ push_subscription : owns
    app_user ||--o{ user_action : actor_or_subject
    app_user ||--o{ issue : assignee
    app_user ||--o{ issue_comment : author
    app_user ||--o{ issue_saved_view : owns
    app_user ||--o{ project_share_link : created_by

    user_group ||--o{ user_group_membership : has
    user_group ||--o{ project_group_access : grants

    project ||--o{ project_membership : has
    project ||--o{ project_group_access : has
    project ||--o{ project_api_key : has
    project ||--o{ project_share_link : has
    project ||--o{ issue : has
    project ||--o{ event : denormalized
    project ||--o{ perf_transaction : has
    project ||--o{ daily_project_stat : has
    project ||--o{ notification_destination : has
    project ||--o{ project_threshold_rule : has
    project ||--o{ issue_saved_view : has

    issue ||--o{ event : has
    issue ||--o{ issue_history : has
    issue ||--o{ issue_comment : has
    issue ||--o| issue : duplicate_of
    issue ||--o{ project_share_link : scoped

    perf_transaction ||--o{ perf_span : has

    notification_destination ||--o{ notification_delivery_attempt : has
    notification_destination ||--o{ notification_digest_buffer : buffers
```

---

## Identity

```mermaid
erDiagram
    app_user {
        int id PK
        string uuid UK
        string email UK
        string display_name
        json roles
        string password
        datetime password_changed_at
        string preferred_locale
        string preferred_theme
        string preferred_content_width
        string preferred_ui_density
        string preferred_motion
        string preferred_font_scale
        string preferred_contrast
        string preferred_sidebar
        json preferred_collapsed_issue_panels
        datetime product_tour_seen_at
        json product_tour_seen_pages
        bool push_notifications_enabled
        bool enabled
        datetime last_activity_at
        datetime created_at
        datetime updated_at
        int created_by_id FK
        int updated_by_id FK
    }

    password_history {
        int id PK
        int user_id FK
        string password
        datetime created_at
    }

    user_group {
        int id PK
        string uuid UK
        string name
        string slug UK
        text description
        datetime created_at
        datetime updated_at
        int created_by_id FK
        int updated_by_id FK
    }

    user_group_membership {
        int id PK
        int user_group_id FK
        int user_id FK
        datetime created_at
    }

    user_action {
        int id PK
        string action
        json context
        string ip_address
        int actor_id FK
        int subject_user_id FK
        datetime created_at
    }

    app_user ||--o{ password_history : CASCADE
    user_group ||--o{ user_group_membership : CASCADE
    app_user ||--o{ user_group_membership : CASCADE
    app_user ||--o{ user_action : actor
    app_user ||--o{ user_action : subject
```

---

## Project & access

```mermaid
erDiagram
    project {
        int id PK
        string uuid UK
        string name
        string slug UK
        text description
        int retention_days
        int retention_max_events
        int ingest_rate_limit_per_minute
        int event_quota_daily
        bool ingest_enabled
        datetime created_at
        datetime updated_at
        int created_by_id FK
        int updated_by_id FK
    }

    project_api_key {
        int id PK
        int project_id FK
        string public_key UK
        text secret_key "encrypted"
        string label
        bool active
        datetime created_at
    }

    project_membership {
        int id PK
        int project_id FK
        int user_id FK
        string role
        datetime created_at
    }

    project_group_access {
        int id PK
        string uuid UK
        int project_id FK
        int user_group_id FK
        string role
        datetime created_at
    }

    project_share_link {
        int id PK
        string uuid UK
        int project_id FK
        int issue_id FK
        int created_by_id FK
        string token_hash UK
        datetime expires_at
        datetime revoked_at
        datetime last_used_at
        datetime created_at
    }

    project ||--o{ project_api_key : CASCADE
    project ||--o{ project_membership : CASCADE
    project ||--o{ project_group_access : CASCADE
    project ||--o{ project_share_link : CASCADE
```

Roles on memberships / group access: `owner` | `admin` | `member` | `viewer`.

---

## Issues & events

```mermaid
erDiagram
    issue {
        int id PK
        string uuid UK
        int project_id FK
        int assignee_id FK
        int duplicate_of_id FK
        string fingerprint
        string title
        string culprit
        string level
        string status
        string priority
        int event_count
        datetime first_seen
        datetime last_seen
        string first_release
        string last_release
        string last_environment
    }

    event {
        int id PK
        int project_id FK
        int issue_id FK
        string event_id
        string environment
        string release_version
        string platform
        string php_version
        string symfony_version
        string user_identifier
        json payload
        datetime event_timestamp
        datetime received_at
    }

    issue_history {
        int id PK
        int issue_id FK
        int actor_id FK
        int from_assignee_id FK
        int to_assignee_id FK
        string kind
        string from_status
        string to_status
        datetime created_at
    }

    issue_comment {
        int id PK
        string uuid UK
        int issue_id FK
        int author_id FK
        text body
        datetime created_at
    }

    issue_saved_view {
        int id PK
        string uuid UK
        int user_id FK
        int project_id FK
        string name
        json query_json
        datetime created_at
    }

    project ||--o{ issue : CASCADE
    project ||--o{ event : CASCADE
    issue ||--o{ event : CASCADE
    issue ||--o{ issue_history : CASCADE
    issue ||--o{ issue_comment : CASCADE
    issue ||--o| issue : duplicate_of
    project ||--o{ issue_saved_view : CASCADE
```

Uniqueness: `(project_id, fingerprint)` on `issue`; `(project_id, event_id)` on `event`. FULLTEXT index on `issue(title, culprit)`.

---

## Performance & analytics

```mermaid
erDiagram
    perf_transaction {
        int id PK
        string uuid UK
        int project_id FK
        string event_id
        string transaction_name
        float duration_ms
        int span_count
        int n_plus_one_count
        json payload
        datetime received_at
    }

    perf_span {
        int id PK
        int transaction_id FK
        string span_id
        string op
        string description
        float duration_ms
        bool n_plus_one_candidate
    }

    daily_project_stat {
        int id PK
        int project_id FK
        date stat_date
        int error_count
        int transaction_count
        int n_plus_one_count
    }

    project ||--o{ perf_transaction : CASCADE
    perf_transaction ||--o{ perf_span : CASCADE
    project ||--o{ daily_project_stat : CASCADE
```

Uniqueness: `(project_id, stat_date)` on `daily_project_stat`.

---

## Notifications & push

```mermaid
erDiagram
    notification_destination {
        int id PK
        string uuid UK
        int project_id FK
        string label
        string type
        text endpoint_url "encrypted"
        bool enabled
        json categories
        bool quiet_hours_enabled
        string quiet_hours_timezone
        string quiet_hours_start
        string quiet_hours_end
        bool digest_enabled
        datetime last_delivery_at
        bool last_delivery_success
        text last_delivery_error
        datetime created_at
        datetime updated_at
    }

    notification_delivery_attempt {
        int id PK
        int destination_id FK
        datetime attempted_at
        bool successful
        text error_snippet
    }

    notification_digest_buffer {
        int id PK
        int destination_id FK
        json payload
        datetime created_at
    }

    project_threshold_rule {
        int id PK
        string uuid UK
        int project_id FK
        string label
        bool enabled
        int error_count
        int window_minutes
        int cooldown_minutes
        string environment
        string release_version
        datetime last_fired_at
        datetime created_at
        datetime updated_at
    }

    push_subscription {
        int id PK
        int user_id FK
        string endpoint_hash UK
        text endpoint "encrypted"
        text p256dh "encrypted"
        text auth_token "encrypted"
        string content_encoding
        string user_agent
        datetime created_at
        datetime updated_at
    }

    project ||--o{ notification_destination : CASCADE
    project ||--o{ project_threshold_rule : CASCADE
    notification_destination ||--o{ notification_delivery_attempt : CASCADE
    notification_destination ||--o{ notification_digest_buffer : CASCADE
    app_user ||--o{ push_subscription : CASCADE
```

---

## Instance settings & appearance

```mermaid
erDiagram
    instance_settings {
        int id PK "always 1"
        text mailer_dsn "encrypted"
        text mailer_from "encrypted"
        datetime setup_completed_at
        bool mercure_enabled
        text mercure_url "encrypted"
        text mercure_public_url "encrypted"
        text mercure_jwt_secret "encrypted"
        datetime created_at
        datetime updated_at
        int created_by_id FK
        int updated_by_id FK
    }

    site_appearance {
        int id PK "always 1"
        string brand_name
        string brand_eyebrow
        string accent_color
        string accent_deep_color
        string accent_color_dark
        string accent_deep_color_dark
        string danger_color
        string danger_color_dark
        datetime created_at
        datetime updated_at
        int created_by_id FK
        int updated_by_id FK
    }
```

Admin UI: **Administration → Mailer** / **Mercure** / **Appearance**. See [MERCURE.md](MERCURE.md) and [PRODUCTION.md](PRODUCTION.md#field-encryption-key-halite).

---

## Related

- Architecture flows: [ARCHITECTURE.md](ARCHITECTURE.md)
- Migrations: `migrations/`
- Local MySQL bind mount: `./.data/mysql` in [`compose.yaml`](../compose.yaml)
