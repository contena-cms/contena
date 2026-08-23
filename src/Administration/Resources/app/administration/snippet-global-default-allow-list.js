/**
 *
 * Snippet keys that are allowed to keep a translation identical to a `global.default` entry in
 * every locale. Take the key from the `ct-core-rules/require-global-default-use` error and add it
 * here — with a comment explaining why the duplicate is intentional.
 *
 * An entry matches either a single key or a whole namespace: `ct-privileges` covers
 * `ct-privileges.roles.editor` and everything else below it.
 */
module.exports = [
    /**
     * Role and permission-type labels are resolved through a runtime-composed key
     * (`ct-privileges.roles.${role}` / `ct-privileges.permissionType.` + type), so the duplicates
     * cannot be referenced statically and the whole namespace must keep its own translations.
     */
    'ct-privileges.roles',
    'ct-privileges.permissionType',

    /** Log level labels are resolved dynamically via `ct-settings-logging.list.level${level}`. */
    'ct-settings-logging.list',

    /**
     * Reachable through the dynamic quick-action key `ct-media.sidebar.actions.${option.id}`, so the
     * entry has to stay even though some call sites reference it statically.
     */
    'ct-media.sidebar.actions.delete',
];
