/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/prefer-promise-reject-errors, ct-deprecation-rules/private-feature-declarations */
function getRepository(entityName: string, origin: string) {
    const app = Contena.Store.get('contenaApps').apps.find((candidate) => {
        const sources = [candidate.mainModule?.source, ...candidate.modules.map((module) => module.source)].filter(Boolean);
        return sources.some((source) => source?.startsWith(origin));
    });

    if (!app) {
        throw new Error(`Could not find an App for origin "${origin}"`);
    }

    return Contena.Service('repositoryFactory').create(entityName as $TSFixMe);
}

function filterContext(result: any, customContext: any): void {
    if (result === null || result === 'undefined' || typeof result !== 'object') return;

    Object.keys(result).forEach((key) => {
        if (key === 'context') {
            Object.keys(result[key]).forEach((contextKey) => {
                if (!customContext?.[contextKey]) delete result[key][contextKey];
            });
            return;
        }
        filterContext(result[key], customContext);
    });
}

const apiContext = (context: any) => ({ ...Contena.Context.api, ...context });

export default function initializeExtensionDataLoader(): void {
    Contena.ExtensionAPI.handle('repositorySearch', async ({ entityName, criteria = new Contena.Data.Criteria(), context }, additionalInformation) => {
        try {
            const result = await getRepository(entityName, additionalInformation._event_.origin).search(criteria, apiContext(context));
            filterContext(result, context);
            return result;
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositoryGet', ({ entityName, id, criteria = new Contena.Data.Criteria(), context }, additionalInformation) => {
        try {
            const result = getRepository(entityName, additionalInformation._event_.origin).get(id, apiContext(context), criteria);
            filterContext(result, context);
            return result;
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositorySave', ({ entityName, entity, context }, additionalInformation) => {
        try {
            return getRepository(entityName, additionalInformation._event_.origin).save(entity, apiContext(context)) as Promise<void>;
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositoryClone', ({ entityName, behavior, entityId, context }, additionalInformation) => {
        try {
            const result = getRepository(entityName, additionalInformation._event_.origin).clone(entityId, behavior, apiContext(context));
            filterContext(result, context);
            return result;
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositoryHasChanges', ({ entityName, entity }, additionalInformation) => {
        try {
            return getRepository(entityName, additionalInformation._event_.origin).hasChanges(entity);
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositorySaveAll', ({ entityName, entities, context }, additionalInformation) => {
        try {
            return getRepository(entityName, additionalInformation._event_.origin).saveAll(entities, apiContext(context)) as Promise<void>;
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositoryDelete', ({ entityName, entityId, context }, additionalInformation) => {
        try {
            return getRepository(entityName, additionalInformation._event_.origin).delete(entityId, apiContext(context)) as unknown as Promise<void>;
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Contena.ExtensionAPI.handle('repositoryCreate', ({ entityName, entityId, context }, additionalInformation) => {
        try {
            const result = getRepository(entityName, additionalInformation._event_.origin).create(apiContext(context), entityId);
            filterContext(result, context);
            return result;
        } catch (error) {
            return Promise.reject(error);
        }
    });
}
