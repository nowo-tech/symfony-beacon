<?php

declare(strict_types=1);

namespace App\Identity;

/**
 * Catalog of recorded actions for the user activity timeline.
 *
 * String values are stable keys used in Twig translations (`users.activity.action.*`)
 * and stored in `user_action.action`.
 */
enum UserActionType: string
{
    /** Instance admin created a Beacon account. */
    case UserCreated = 'user.created';

    /** Instance role changed between user and admin. */
    case UserRoleChanged = 'user.role_changed';

    /** Account enabled (UserKit); user may sign in again. */
    case UserEnabled = 'user.enabled';

    /** Account disabled (UserKit); login is blocked. */
    case UserDisabled = 'user.disabled';

    case GroupCreated = 'group.created';
    case GroupUpdated = 'group.updated';
    case GroupDeleted = 'group.deleted';
    case GroupMemberAdded = 'group.member_added';
    case GroupMemberRemoved = 'group.member_removed';

    case ProjectMemberAdded = 'project.member_added';
    case ProjectMemberRoleChanged = 'project.member_role_changed';
    case ProjectMemberRemoved = 'project.member_removed';

    /** Project ownership transferred to another direct member. */
    case ProjectOwnershipTransferred = 'project.ownership_transferred';

    /** User group linked to a project (admin/member role only). */
    case ProjectGroupLinked = 'project.group_linked';
    case ProjectGroupRoleChanged = 'project.group_role_changed';
    case ProjectGroupUnlinked = 'project.group_unlinked';

    /** Authenticated user opened a project's issues list. */
    case ProjectOpened = 'project.opened';

    /** Authenticated user opened project settings. */
    case ProjectSettingsViewed = 'project.settings_viewed';

    /** Authenticated user created a project. */
    case ProjectCreated = 'project.created';

    /** Project API key created. */
    case ProjectApiKeyCreated = 'project.api_key_created';

    /** Project API key revoked (inactive). */
    case ProjectApiKeyRevoked = 'project.api_key_revoked';

    /** Project API key rotated (new secret; previous deactivated). */
    case ProjectApiKeyRotated = 'project.api_key_rotated';

    /** Platform admin suspended Envelope ingest for a project. */
    case ProjectSuspended = 'project.suspended';

    /** Platform admin resumed Envelope ingest for a project. */
    case ProjectResumed = 'project.resumed';

    /** Platform admin entered view-as-member mode. */
    case ProjectViewAsStarted = 'project.view_as_started';

    /** Platform admin exited view-as-member mode. */
    case ProjectViewAsEnded = 'project.view_as_ended';

    /** Project event/issue history cleared. */
    case ProjectHistoryCleared = 'project.history_cleared';

    /** Project deleted. */
    case ProjectDeleted = 'project.deleted';

    /** Authenticated user opened an issue detail page. */
    case IssueOpened = 'issue.opened';

    /** Issue assignee changed. */
    case IssueAssigned = 'issue.assigned';

    /** Issue status changed. */
    case IssueStatusChanged = 'issue.status_changed';

    /** Authenticated user opened an event detail page. */
    case EventOpened = 'event.opened';

    /** Issue priority changed. */
    case IssuePriorityChanged = 'issue.priority_changed';

    /** Comment added on an issue. */
    case IssueCommented = 'issue.commented';

    /** Issue marked as duplicate of another. */
    case IssueMarkedDuplicate = 'issue.marked_duplicate';

    /** Duplicate issue events merged into a canonical issue. */
    case IssueMerged = 'issue.merged';

    /** Authenticated user opened project performance. */
    case PerformanceOpened = 'performance.opened';

    /** Authenticated user opened project analytics. */
    case AnalyticsOpened = 'analytics.opened';

    /** Magic login link requested (email may have been sent). */
    case MagicLoginRequested = 'auth.magic_login_requested';

    /** Magic login link consumed successfully. */
    case MagicLoginConsumed = 'auth.magic_login_consumed';

    /** Project share link created. */
    case ProjectShareLinkCreated = 'project.share_link_created';

    /** Project share link revoked. */
    case ProjectShareLinkRevoked = 'project.share_link_revoked';

    /** Project share link opened (viewer grant applied). */
    case ProjectShareLinkOpened = 'project.share_link_opened';
}
