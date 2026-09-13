/**
 * @ct-package framework
 */
import useTheme from '../composables/use-theme';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeTheme(): void {
    useTheme();

    // Documents restored from the back/forward cache do not boot again, so the
    // persisted preference has to be re-applied to catch up with other documents.
    // eslint-disable-next-line listeners/no-inline-function-event-listener,listeners/no-missing-remove-event-listener
    window.addEventListener('pageshow', (event: PageTransitionEvent) => {
        if (event.persisted) {
            useTheme().syncPersistedTheme();
        }
    });
}
