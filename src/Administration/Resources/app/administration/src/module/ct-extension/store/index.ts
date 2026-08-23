import type { ContenaClass } from 'src/core/contena';
import useSession from '../../../app/composables/use-session';
import './extensions.store';

let initialLoad = false;

/**
 * @private
 */
export default function initState(Contena: ContenaClass): void {
    Contena.Vue.watch(useSession().languageId, async () => {
        if (!Contena.Service('acl').can('system.plugin_maintain')) {
            return;
        }

        // Always on page load setAdminLocale will be called once. Catch it to not load refresh extensions
        if (!initialLoad) {
            initialLoad = true;
            return;
        }

        await Contena.Service('contenaExtensionService').updateExtensionData(false);
    });
}
