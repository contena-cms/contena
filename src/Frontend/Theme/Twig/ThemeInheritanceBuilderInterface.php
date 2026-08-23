<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Twig;

interface ThemeInheritanceBuilderInterface
{
    /**
     * Reorders bundles according to theme-specific template inheritance rules.
     *
     * WHAT THIS DOES:
     * Themes can override the default template loading order by defining custom inheritance
     * chains in their theme.json file. This method applies those rules to reorder bundles
     * while preserving their original priority values.
     *
     * HOW IT WORKS:
     * 1. Receives bundles sorted by priority (bundle name => priority value)
     * 2. Reads inheritance rules from the active theme's configuration
     * 3. Reorders bundles according to these rules
     * 4. Returns reordered bundles with original priority values preserved
     *
     * THEME.JSON CONFIGURATION:
     * Themes define inheritance order in Resources/theme.json:
     * ```json
     * {
     *     "views": [
     *         "@Frontend",
     *         "@CtPayPal",
     *         "@Plugins",
     *         "@MyNewTheme"
     *     ]
     * }
     * ```
     *
     * SPECIAL PLACEHOLDERS:
     * - @Frontend: The base Contena Frontend theme
     * - @CtPayPal: Explicitly positions the PayPal plugin in the hierarchy
     * - @Plugins: Wildcard for all plugins not explicitly mentioned
     * - @MyNewTheme: The theme itself (usually last for highest priority)
     *
     * TEMPLATE RESOLUTION ORDER:
     * The array order determines template override precedence:
     * - First entry: Templates checked first (lowest priority)
     * - Last entry: Templates checked last (can override all others)
     *
     * In the example above, template resolution order would be:
     * 1. Check Frontend (base templates)
     * 2. Check CtPayPal (can override Frontend)
     * 3. Check all other plugins (can override above)
     * 4. Check MyNewTheme (final override, highest priority)
     *
     * @param array<string, int> $bundles Bundle names mapped to priority values (pre-sorted)
     * @param array<int|string, bool> $themes Active theme names with activation status
     *
     * @return array<string, int> Reordered bundles maintaining original priority values
     */
    public function build(array $bundles, array $themes): array;
}
