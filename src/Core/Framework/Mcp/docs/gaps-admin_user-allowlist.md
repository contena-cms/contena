# Gap: Per-admin_user MCP allowlist

Tracked in [blog-epic-backlog.md](blog-epic-backlog.md) — Workstream 1, row "Per-admin_user MCP allowlist".

## Status: Implemented

## What was built

### Auth surface (before vs. after)

| Auth mode | Before | After |
|-----------|--------|-------|
| Integration access key + secret | `integration.mcp_allowlist` | unchanged |
| AdminUser access key + secret | rejected (`unsupportedKeyType`) | `admin_user.mcp_allowlist` via admin_user_id lookup |
| Bearer JWT, password grant | `unrestricted()` | `admin_user.mcp_allowlist` via `ATTRIBUTE_OAUTH_USER_ID` |
| Bearer JWT, client_credentials | `unrestricted()` | `integration.mcp_allowlist` via `ATTRIBUTE_OAUTH_CLIENT_ID` |
| Integration + `sw-app-admin_user-id` (Copilot) | `integration.mcp_allowlist` only | intersect(`integration.mcp_allowlist`, `admin_user.mcp_allowlist`) |

A `NULL` allowlist used to mean unrestricted for that principal. Since the null-bypass fix it means the opposite: no capabilities at all, for every principal except an administrator admin_user (`admin = true`), who still bypasses the allowlist regardless of auth mode. Integrations have no bypass, including those flagged `admin`.

### Architecture decision: allowlist lives on the admin_user, not the access key

An earlier iteration placed `mcp_allowlist` on `admin_user_access_key` (one allowlist per key). This was changed: the allowlist lives on `admin_user` (one allowlist per admin_user).

Why:

1. **Consistency with the permission model.** ACL roles and all other permissions sit on the admin_user entity. A per-admin_user allowlist is the natural complement.
2. **Copilot intersection works naturally.** When an integration sends `sw-app-admin_user-id`, the platform already has a admin_user identity to look up. Intersecting `integration.mcp_allowlist` with `admin_user.mcp_allowlist` requires nothing more than reading from `admin_user`. Per-key allowlists would have required a separate admin_user lookup anyway.
3. **Bearer JWT works.** Password-grant JWTs carry the admin_user ID in the `sub` claim, which `SymfonyBearerTokenValidator` stores in `ATTRIBUTE_OAUTH_USER_ID`. Routing these sessions through `forUserId()` requires no new infra.
4. **Simpler Admin UI.** One card on the admin_user detail page, not a per-key modal in the access key grid.

Tradeoff: a admin_user cannot scope individual access keys differently. All keys for a admin_user share the same allowlist. This is intentional — the unit of trust is the admin_user, not the key.

## Implementation

### Migration

**File:** `src/Core/Migration/V6_7/Migration1778142666AddMcpAllowlistToUser.php`

Adds `mcp_allowlist JSON NULL` to the `admin_user` table:

```php
public function update(Connection $connection): void
{
    $this->addColumn($connection, 'admin_user', 'mcp_allowlist', 'JSON');
}

public function updateDestructive(Connection $connection): void {}
```

The `admin_user_access_key.mcp_allowlist` column from an earlier experiment was dropped manually (not released).

### DAL

**`AdminUserDefinition.php`** — added `JsonField('mcp_allowlist', 'mcpAllowlist')` after `BoolField('admin', 'admin')`.

**`AdminUserEntity.php`** — added `$mcpAllowlist`, `getMcpAllowlist()`, `setMcpAllowlist()`.

`AdminUserAccessKeyDefinition.php` and `AdminUserAccessKeyEntity.php` are unchanged (the earlier `mcp_allowlist` field was removed).

### `McpAuthenticationListener.php`

Accepts both `'integration'` and `'admin_user'` key origins. When neither `sw-access-key` nor `sw-secret-access-key` header is present, the listener returns without throwing — standard bearer JWT auth handles the request downstream.

### `McpAllowlistProvider.php`

`forCurrentRequest()` branches on all auth modes:

```php
$clientId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

if ($clientId !== '') {
    $origin = AccessKeyHelper::getOrigin($clientId);

    if ($origin === 'admin_user') {
        return $this->forUserAccessKey($clientId);       // → forUserId()
    }
    if ($origin === 'integration') {
        $appUserId = $request->headers->get(PlatformRequest::HEADER_APP_USER_ID);
        if ($appUserId !== null && Uuid::isValid($appUserId)) {
            return $this->intersect($this->forAccessKey($clientId), $this->forUserId($appUserId));
        }
        return $this->forAccessKey($clientId);
    }
}

// Bearer JWT password grant: ATTRIBUTE_OAUTH_CLIENT_ID = 'administration'
$admin_userId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
if ($admin_userId !== '' && Uuid::isValid($admin_userId)) {
    return $this->forUserId($admin_userId);
}

return McpAllowlist::blocked();
```

`forUserId()` reads `mcp_allowlist` and `admin` from the `admin_user` table. Admin users get `unrestricted()`; every other row is parsed with `McpAllowlist::restrictedFromJson()`, which resolves anything not explicitly selected to an empty list. `forUserAccessKey()` looks up `admin_user_id` from `admin_user_access_key` and delegates to `forUserId()`. `intersect()` takes the element-wise intersection of two allowlists (null = unrestricted, treated as "allow all" when intersecting, which after the fix only an administrator admin_user can contribute).

### `AdminUserMcpAllowlistController.php`

**File:** `src/Core/Framework/Mcp/Controller/AdminUserMcpAllowlistController.php`

```
POST /api/_action/admin_user/{admin_userId}/mcp-allowlist
```

ACL: `api_action_user_mcp-allowlist` (mapped to `users_and_permissions.editor`).

Body: `{ "allowlist": { "tools": [...], "resources": [...], "prompts": [...] } | null }`.

Mirrors `IntegrationMcpAllowlistController` exactly, operating on the `admin_user` repository.

The previous `AdminUserAccessKeyMcpAllowlistController` (`POST /api/_action/admin_user-access-key/{id}/mcp-allowlist`) was deleted.

### Admin UI

**MCP allowlist card** on the admin_user detail page (below the Integrations card):

```twig
<mt-card :title="...">
    <sw-integration-mcp-allowlist
        :allowlist="admin_user.mcpAllowlist"
        :disabled="!acl.can('users_and_permissions.editor') || !$route.params.id"
        :is-admin="admin_user.admin"
        :granted-privileges="mcpGrantedPrivileges"
        @update:allowlist="onMcpAllowlistUpdate"
    />
</mt-card>
```

`onMcpAllowlistUpdate(allowlist)` calls `POST /api/_action/admin_user/{admin_userId}/mcp-allowlist` and updates `admin_user.mcpAllowlist` in place.

The per-key "Edit MCP allowlist" context menu item and its modal were removed from the access key grid.

**`admin_user.api.service.js`** — `saveMcpAllowlist(admin_userId, allowlist)` calls `POST /api/_action/admin_user/{id}/mcp-allowlist`.

**ACL entry** in `sw-users-permissions/acl/index.js`: `api_action_user_mcp-allowlist` under `editor`.

## Files created / modified

| Action | File |
|--------|------|
| Create | `src/Core/Migration/V6_7/Migration1778142666AddMcpAllowlistToUser.php` |
| Modify | `src/Core/System/AdminUser/AdminUserDefinition.php` |
| Modify | `src/Core/System/AdminUser/AdminUserEntity.php` |
| Modify | `src/Core/System/AdminUser/Aggregate/AdminUserAccessKey/AdminUserAccessKeyDefinition.php` (removed field) |
| Modify | `src/Core/System/AdminUser/Aggregate/AdminUserAccessKey/AdminUserAccessKeyEntity.php` (removed field) |
| Modify | `src/Core/Framework/Mcp/Authentication/McpAuthenticationListener.php` |
| Modify | `src/Core/Framework/Mcp/AllowList/McpAllowlistProvider.php` |
| Delete | `src/Core/Framework/Mcp/Controller/AdminUserAccessKeyMcpAllowlistController.php` |
| Create | `src/Core/Framework/Mcp/Controller/AdminUserMcpAllowlistController.php` |
| Modify | `src/Core/Framework/Mcp/McpException.php` (removed `missingCredentials()`) |
| Modify | `src/Administration/.../sw-users-permissions-admin_user-detail.html.twig` |
| Modify | `src/Administration/.../sw-users-permissions-admin_user-detail/index.js` |
| Modify | `src/Administration/.../sw-users-permissions/acl/index.js` |
| Modify | `src/Administration/.../sw-users-permissions/snippet/en.json` + `de.json` |
| Modify | `src/Administration/Resources/app/administration/src/core/service/api/admin_user.api.service.js` |

## Known gaps / parking lot

- **Access key labels**: admin_user access keys show raw `SWUA...` strings with no human-readable name. Considered but parked — out of scope for this workstream.
- **MCP-spec OAuth**: auth code + PKCE + DCR. Neither `mcp/sdk` nor `symfony/mcp-bundle` provide this. Tracked in separate gap doc.
