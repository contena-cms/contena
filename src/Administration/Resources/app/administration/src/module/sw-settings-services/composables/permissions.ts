/**
 * @sw-package framework
 */
import type { HandleMethod } from '@contena-ag/meteor-admin-sdk/es/channel';
import useSession from 'src/app/composables/use-session';
import { useContenaServicesStore } from '../store/contena-services.store';

let reloadFn: () => void = () => window.location.reload();

/**
 * Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26).
 * @private
 */
export function _reloadPage() {
    reloadFn();
}

/**
 * For testing only.
 * @private
 */
export function __setReloadFn(fn: () => void) {
    reloadFn = fn;
}

/**
 * @private
 */
export async function grantPermissions() {
    const contenaServiceStore = useContenaServicesStore();
    let currentRevision = contenaServiceStore.currentRevision?.revision;

    if (!currentRevision) {
        const sessionStore = useSession();
        const revisionData = await Contena.Service('serviceRegistryClient').getCurrentRevision(
            sessionStore.currentLocale.value ?? 'en-GB',
        );

        contenaServiceStore.revisions = revisionData;
        currentRevision = contenaServiceStore.currentRevision?.revision;
    }

    if (!currentRevision) {
        throw new Error('No revision available');
    }

    await Contena.Service('contenaServicesService').acceptRevision(currentRevision);

    _reloadPage();
}

/**
 * @private
 */
export async function revokePermissions() {
    await Contena.Service('contenaServicesService').revokePermissions();

    _reloadPage();
}

function assertServiceOrigin(origin: string): void {
    const matchingExtensions = Object.values(Contena.Store.get('extensions').extensionsState).filter((extension) => {
        try {
            return new URL(extension.baseUrl).origin === origin;
        } catch {
            return false;
        }
    });

    if (matchingExtensions.length === 0 || !matchingExtensions.every((extension) => extension.sourceType === 'service')) {
        throw new Error('Only Contena Services can access this handler.');
    }
}

/**
 * @private
 */
export const grantPermissionsFromSdk: HandleMethod<'servicePermissionGrant'> = (_message, { _event_ }) => {
    assertServiceOrigin(_event_.origin);

    return grantPermissions();
};

/**
 * Resolves to `true` when the Contena Services consent is already granted or not needed,
 * i.e. the latest revision has been consented to, or Contena Services are disabled.
 *
 * @private
 */
export const isPermissionGrantedFromSdk: HandleMethod<'servicePermissionIsGranted'> = async (_message, { _event_ }) => {
    assertServiceOrigin(_event_.origin);

    const contenaServicesStore = useContenaServicesStore();

    if (!contenaServicesStore.config) {
        contenaServicesStore.config = await Contena.Service('contenaServicesService').getServicesContext();
    }

    if (contenaServicesStore.config?.disabled) {
        return true;
    }

    if (!contenaServicesStore.revisions) {
        const locale = useSession().currentLocale.value ?? 'en-GB';
        contenaServicesStore.revisions = await Contena.Service('serviceRegistryClient').getCurrentRevision(locale);
    }

    return contenaServicesStore.consentGiven;
};
