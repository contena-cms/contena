import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import detailComponent from '../index';

const originalService = Contena.Service;
const wrappers: VueWrapper[] = [];

/** @private */
export const createCollection = <T>(items: T[]) => ({
    first: () => items[0] ?? null,
    total: items.length,
});

/** @private */
export const createMutationResponse = (layout: Array<Record<string, unknown>>, affectedElementIds: string[] = []) => ({
    layout,
    resolutions: {},
    diagnostics: {
        wellFormed: true,
        resolvable: true,
        violations: [],
    },
    affectedElementIds,
    orphaned: [],
    droppedWiring: [],
    droppedProperties: {},
});

/** @private */
export async function createWrapper(
    options: {
        repositories?: Record<string, Record<string, jest.Mock>>;
        services?: Record<string, object>;
        initialLayout?: Record<string, unknown>;
        route?: {
            name: string;
            params: Record<string, string>;
            query?: Record<string, string>;
        };
    } = {},
) {
    const defaultLayout = options.initialLayout ?? {
        id: 'layout-1',
        name: 'Layout',
        rootSource: 'blog',
        layout: [],
    };
    const defaultRepository = {
        create: jest.fn(() => ({})),
        get: jest.fn().mockResolvedValue(defaultLayout),
        search: jest.fn().mockResolvedValue(createCollection([])),
        save: jest.fn().mockResolvedValue(undefined),
        delete: jest.fn().mockResolvedValue(undefined),
    };
    const repositories = options.repositories ?? {};
    const repositoryFactory = {
        create: jest.fn((entityName: string) => repositories[entityName] ?? defaultRepository),
    };
    const defaultServices = {
        contentSystemElementTypeService: { getTypes: jest.fn().mockResolvedValue([]) },
        contentSystemStyleOptionService: { getStyleOptions: jest.fn().mockResolvedValue({}) },
        contentSystemEntityTypeService: {
            getEntityTypes: jest.fn().mockResolvedValue([
                'category',
                'landing_page',
                'blog',
            ]),
        },
        contentSystemLayoutDraftMutationService: {},
    };
    const services = { ...defaultServices, ...options.services };
    Contena.Service = jest.fn(
        (serviceName: string) => services[serviceName as keyof typeof services],
    ) as unknown as typeof Contena.Service;

    const wrapper = shallowMount(detailComponent, {
        global: {
            provide: {
                [routeLocationKey]: {
                    name: 'ct.experience.studio.detail',
                    params: { id: 'layout-1' },
                    query: {},
                    ...options.route,
                },
                [routerKey]: { push: jest.fn().mockResolvedValue(undefined) },
                repositoryFactory,
                acl: { can: () => true },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();

    return { wrapper, repositoryFactory, defaultRepository };
}

/** @private */
export function resetWrappers(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    Contena.Service = originalService;
}
