import Store from 'src/app/store';
import '../store/admin-menu.store';
import '../store/block-override.store';
import 'src/app/store/extension-entry-routes.store';
import 'src/app/store/error.store';
import 'src/app/store/admin-help-center.store';
import 'src/app/store/context.store';
import 'src/app/store/settings-item.store';
import 'src/app/store/system.store';
import 'src/app/store/session.store';
import 'src/app/store/ct-bulk-edit.store';
import 'src/app/store/media-modal.store';

/**
 * @private
 */
export default function initStore() {
    const app = Contena.Application?.view?.app;

    /**
     * This code does two things:
     * 1. Initializing the Pinia singleton by accessing the instance getter.
     * 2. Registering the Pinia plugin with Vue before the first store is trying to be registered.
     */
    if (app) {
        app.use(Store.instance._rootState);
    }
}
