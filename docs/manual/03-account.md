# Account

Signed-in preferences live under **Account settings** (UserKit + AuthKit): profile identity, memberships, security, display, and privacy tools.

Open Account from the avatar menu in the header. Sidebar groups: **Profile**, **Security**, and **Display**. Profile also hosts **My projects**, **My groups**, and **Privacy** sub-tabs.

## Overview (Profile)

**What it is.** The Profile landing view: account card (status, roles, membership metadata) plus editable identity fields such as display name and phone.

**What it contributes.** Confirms who you are signed in as, password-policy status, and shortcuts into Security / Display. Phone verification supports QR login (see [Getting started](01-getting-started.md#qr-phone-sign-in)).

![Account overview / profile](images/account.png)

## Profile form

**What it is.** The editable profile section (display name, phone, related identity fields).

**What it contributes.** Controls how your name appears in member lists and the avatar menu, and keeps phone state aligned with QR sign-in.

![Profile](images/account-profile.png)

## My projects

**What it is.** A personal list of projects you belong to, with role context for each membership.

**What it contributes.** Fast jump to projects without returning to the dashboard filter, and a clear view of ownership vs member roles before anonymizing or transferring access.

![Your projects](images/account-projects.png)

## My groups

**What it is.** Groups your account belongs to (groups are used for project access grants).

**What it contributes.** Explains *why* you can open certain projects when access was granted via a group rather than a direct membership.

![Your groups](images/account-groups.png)

## Security

**What it is.** Password change and security settings for the account.

**What it contributes.** Self-service credential hygiene without admin intervention. Complements instance-wide password policy from the password kits.

![Security](images/account-security.png)

### Security activity

**What it is.** Recent security-relevant events for your account (sign-ins and related activity).

**What it contributes.** Lets you spot unexpected sessions or recovery activity early.

![Security activity](images/account-security-activity.png)

### Devices

**What it is.** Known devices / sessions associated with the account.

**What it contributes.** Review and revoke access from devices you no longer trust.

![Devices](images/account-security-devices.png)

### History

**What it is.** Longer-running security history for audit-style review.

**What it contributes.** Historical context beyond the short activity feed (useful after an incident or password change).

![History](images/account-security-history.png)

## Display

**What it is.** Personal UI preferences: theme defaults, which issue panels you prefer, product tours, and how member notifications appear.

**What it contributes.** Tailors the private UI without changing instance branding. Global day/night and language switching is also available in the header — see [Theme and language](07-appearance.md). Instance brand colours live under [Administration → Appearance](05-admin-and-ops.md#appearance).

![Display](images/account-display.png)

### Issue panels

**What it is.** Preferences for which panels appear on issue views.

**What it contributes.** Reduces clutter on issue detail by keeping only the panels you use (for example AI export or frequency widgets).

![Issue panels](images/account-display-panels.png)

### Tours

**What it is.** Controls for onboarding / product tours.

**What it contributes.** Lets you replay or dismiss guided tours after the first visit.

![Tours](images/account-display-tours.png)

### Notification display

**What it is.** How notification-related UI is shown for your memberships. Companion shot continues the same viewport.

**What it contributes.** Separates *display* of notifications from project-level *destinations and rules* (those live under project Settings → Alerts).

![Notification display](images/account-display-notifications.png)

![Notification display (continued)](images/account-display-notifications-2.png)

## Privacy

**What it is.** GDPR-oriented self-service: download your account data as JSON, and anonymize the account when prerequisites are met.

**What it contributes.**

- **Download JSON** — exports profile, memberships, and security activity (not password hashes; not other users’ data; not project ingest events/issues, which stay with the project until retention purge).  
- **Anonymize account** — irreversible from the UI; requires transferring sole ownership of projects first.

Public legal copy: [Legal and privacy](06-legal-and-privacy.md). Deeper product notes: [LEGAL-AND-COOKIES.md](../product/LEGAL-AND-COOKIES.md).

![Privacy](images/account-privacy.png)

## Next steps

- [Projects and issues](04-projects-and-issues.md)  
- [Administration](05-admin-and-ops.md) (requires `ROLE_ADMIN`)  
