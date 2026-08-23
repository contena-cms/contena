/**
 *
 * Snippet keys in the Frontend's admin modules that may keep a translation identical to a
 * `global.default` entry in every locale. Same format as the Administration's allow list: an entry
 * matches either a single key or a whole namespace (`foo` covers `foo.bar.baz`).
 */
module.exports = [
    /**
     * Theme configuration field labels (`ct-theme.…label`) are auto-constructed from the theme
     * config structure, not authored as reusable UI strings, so they keep their own labels.
     */
    'ct-theme',
];
