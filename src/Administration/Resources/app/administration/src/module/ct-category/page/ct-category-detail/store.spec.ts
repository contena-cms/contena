import { createPinia, setActivePinia } from 'pinia';
import type Repository from '../../../../core/data/repository.data';
import type { ContextStore } from '../../../../app/store/context.store';
import './store';

const newLandingPageMock = {
    id: '12345',
};

const existingLandingPageMock = {
    id: '67890',
};

const categoriesMock: Record<string, Partial<EntitySchema.Entities['category']>> = {
    'without-parent': {
        id: '12345',
    },
    'with-parent': {
        id: '67890',
        parentId: 'parent',
    },
    parent: {
        id: '111213',
        footerChannels: [{ typeId: '12345' }] as EntitySchema.EntityCollection<'channel'>,
    },
};

const apiContextMock = {} as ContextStore['api'];

const landingPageRepositoryMock = {
    create: jest.fn(() => ({
        ...newLandingPageMock,
    })),
    get: jest.fn(() => Promise.resolve(existingLandingPageMock)),
} as unknown as Repository<'landing_page'>;

const categoryRepositoryMock = {
    get: jest.fn((id: string) => Promise.resolve({ ...categoriesMock[id] })),
} as unknown as Repository<'category'>;

describe('ct-category.store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should have the initial state', () => {
        const ctCategoryDetailStore = Contena.Store.get('ctCategoryDetail');

        expect(ctCategoryDetailStore.landingPage).toBeNull();
        expect(ctCategoryDetailStore.category).toBeNull();
        expect(ctCategoryDetailStore.isCategoryColumn).toBe(false);
        expect(ctCategoryDetailStore.customFieldSets).toEqual([]);
        expect(ctCategoryDetailStore.landingPagesToDelete).toBeUndefined();
        expect(ctCategoryDetailStore.categoriesToDelete).toBeUndefined();
    });

    it('loads an active landing page creating a new one', async () => {
        const ctCategoryDetailStore = Contena.Store.get('ctCategoryDetail');

        await ctCategoryDetailStore.loadActiveLandingPage({
            id: 'create',
            repository: landingPageRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(landingPageRepositoryMock.create).toHaveBeenCalledWith(apiContextMock);
        expect(ctCategoryDetailStore.landingPage).toStrictEqual(newLandingPageMock);
    });

    it('loads an active landing page loading an existing one', async () => {
        const ctCategoryDetailStore = Contena.Store.get('ctCategoryDetail');

        await ctCategoryDetailStore.loadActiveLandingPage({
            id: '67890',
            repository: landingPageRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(landingPageRepositoryMock.get).toHaveBeenCalledWith('67890', apiContextMock, expect.anything());
        expect(ctCategoryDetailStore.landingPage).toStrictEqual(existingLandingPageMock);
    });

    it('loads an active category', async () => {
        const ctCategoryDetailStore = Contena.Store.get('ctCategoryDetail');

        await ctCategoryDetailStore.loadActiveCategory({
            id: 'without-parent',
            repository: categoryRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(categoryRepositoryMock.get).toHaveBeenCalledWith('without-parent', apiContextMock, expect.anything());
        expect(ctCategoryDetailStore.isCategoryColumn).toBe(false);
        expect(ctCategoryDetailStore.category).toStrictEqual(categoriesMock['without-parent']);
    });

    it('loads an active category with parent', async () => {
        const ctCategoryDetailStore = Contena.Store.get('ctCategoryDetail');

        await ctCategoryDetailStore.loadActiveCategory({
            id: 'with-parent',
            repository: categoryRepositoryMock,
            apiContext: apiContextMock,
        });

        // eslint-disable-next-line @typescript-eslint/unbound-method
        expect(categoryRepositoryMock.get).toHaveBeenCalledWith('with-parent', apiContextMock, expect.anything());
        expect(ctCategoryDetailStore.isCategoryColumn).toBe(true);
        expect(ctCategoryDetailStore.category).toStrictEqual({
            ...categoriesMock['with-parent'],
            parent: categoriesMock.parent,
        });
    });
});
