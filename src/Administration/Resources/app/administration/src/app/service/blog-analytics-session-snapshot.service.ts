/**
 * @sw-package framework
 */
import useTheme from 'src/app/composables/use-theme';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

/**
 * Blog analytics event carrying the global Admin setup of the current admin_user.
 *
 * @private
 */
export const SESSION_SNAPSHOT_EVENT = 'admin_session_started';

/**
 * Reports the global Admin setup of the current admin_user to blog analytics. Sent once
 * per Admin boot after the admin_user was identified. `theme` is the applied outcome, `theme_preference`
 * the stored choice, which may be `system`.
 *
 * @private
 */
export function trackSessionSnapshot(): void {
    const { theme, resolvedTheme } = useTheme();

    Contena.Telemetry.track({
        eventName: SESSION_SNAPSHOT_EVENT,
        theme: resolvedTheme.value,
        theme_preference: theme.value,
        module_icon_colors: useModuleIconColors().enabled.value,
    });
}
