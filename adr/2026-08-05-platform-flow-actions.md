---
title: Retain platform-oriented Flow actions
date: 2026-08-05
area: framework
tags: [framework, flow, administration, user, notification]
---

## Context

Contena retains Flow Builder as general-purpose workflow infrastructure, but the extracted platform no longer contains contena's Customer, Order, SalesChannel, or Commerce actions. This left the Administration with email and stop-flow actions only, even when a trigger provided a Contena user.

contena `trunk` at `94e3b31ca1dced09d375671a760bc4752dde6620` provides reusable implementations for customer status, customer tags, and customer custom fields in:

- `ChangeCustomerStatusAction`
- `AddCustomerTagAction`
- `RemoveCustomerTagAction`
- `SetCustomerCustomFieldAction`
- `CustomFieldActionTrait`

Those implementations cannot be retained verbatim because Contena uses the `User` identity domain and user-scoped Custom Field relations. contena has no action that creates a native Administration notification. Searches for `CreateNotificationAction` and `action.notification.create` across all 747 locally available upstream remote refs returned no matching history.

## Decision

The customer actions are adapted with the smallest domain-specific changes:

- status assignment writes the upstream-compatible `user.active` boolean through the User repository;
- tag actions reuse the existing `user.tags` association and `user_tag` mapping;
- custom-field updates reuse the upstream update semantics while accepting only Custom Fields related to the `user` entity;
- every user action requires `UserAware` and reads the stored `userId`.

The Administration presents tag actions in one independent **Tags** group. They are not duplicated below a user group, although their internal action names remain user-scoped because the affected entity is a Contena user.

`CreateNotificationAction` is a minimal new internal Flow action. It delegates persistence and delivery semantics to the existing `NotificationService`, validates its status and privilege list, and introduces no parallel notification infrastructure.

Existing media and mail events implement `FlowEventAware` and expose their scalar values so the retained platform operations can be selected as real triggers.

## Consequences

- No database migration is required; the actions reuse existing user, tag, custom-field, notification, and Flow storage.
- Flow configurations can persist the new action names and their configuration payloads, so they are developer-facing contracts documented in the release notes.
- User-aware actions are unavailable when an event cannot provide a stable user identifier.
- User status action configurations persist `{ active: bool }`; no user state-machine or state enter/leave triggers are introduced.
- The notification action is classified as `NEW`; the other user actions and their UI are classified as upstream `ADAPT` work.
