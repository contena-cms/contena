import type { Entity } from '@contena/meteor-admin-sdk/es/_internals/data/Entity';
import type CriteriaType from '@contena/meteor-admin-sdk/es/data/Criteria';
import type Repository from '../../../../core/data/repository.data';
import type { ContextStore } from '../../../../app/store/context.store';

const { Criteria } = Contena.Data;

interface LoadPayload<EntityName extends keyof EntitySchema.Entities> {
    repository: Repository<EntityName>;
    id: string;
    apiContext: ContextStore['api'];
    criteria?: CriteriaType;
}

/**
 * @ct-package inventory
 */
const ctCategoryDetailStore = Contena.Store.register({
    id: 'ctCategoryDetail',

    state: () => ({
        landingPage: null as Entity<'landing_page'> | null,
        category: null as Entity<'category'> | null,
        isCategoryColumn: false,
        customFieldSets: [],
        landingPagesToDelete: undefined,
        categoriesToDelete: undefined,
    }),

    actions: {
        loadActiveLandingPage({ repository, id, apiContext, criteria = new Criteria(1, 25) }: LoadPayload<'landing_page'>) {
            if (id === 'create') {
                const landingPage = repository.create(apiContext);
                this.landingPage = landingPage;
                return Promise.resolve();
            }

            return repository.get(id, apiContext, criteria).then((landingPage) => {
                this.landingPage = landingPage;
            });
        },

        loadActiveCategory({ repository, id, apiContext, criteria = new Criteria(1, 25) }: LoadPayload<'category'>) {
            return repository
                .get(id, apiContext, criteria)
                .then((category) => {
                    if (category) {
                        this.isCategoryColumn = false;
                        if (category.parentId) {
                            const parentCriteria = new Criteria(1, 25);
                            parentCriteria.addAssociation('footerChannels');

                            return repository.get(category.parentId, apiContext, parentCriteria).then((parent) => {
                                category.parent = parent ?? undefined;

                                this.isCategoryColumn = category.parent?.footerChannels?.length !== 0;

                                return category;
                            });
                        }
                    }
                    return category;
                })
                .then((category) => {
                    this.category = category;
                });
        },
    },
});

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export type CtCategoryDetailStore = ReturnType<typeof ctCategoryDetailStore>;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default ctCategoryDetailStore;
