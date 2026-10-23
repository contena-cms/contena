/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-user-list', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                    meta: {
                        $module: {
                            icon: 'regular-content',
                        },
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        create: () => {
                            return Promise.resolve(
                                entity === 'user'
                                    ? {
                                          data: [
                                              {
                                                  id: '1a2b3c',
                                                  entity: 'user',
                                                  userId: 'd4c3b2a1',
                                                  blogId: 'd4c3b2a1',
                                                  channelId: 'd4c3b2a1',
                                              },
                                          ],
                                          total: 1,
                                      }
                                    : [],
                            );
                        },
                        search: () => {
                            return Promise.resolve(
                                entity === 'user'
                                    ? {
                                          data: [
                                              {
                                                  id: '1a2b3c',
                                                  entity: 'user',
                                                  userId: 'd4c3b2a1',
                                                  blogId: 'd4c3b2a1',
                                                  channelId: 'd4c3b2a1',
                                                  sourceEntitiy: 'user',
                                                  createdById: '123213132',
                                              },
                                          ],
                                          total: 1,
                                      }
                                    : [],
                            );
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                filterFactory: {},
                searchRankingService: {
                    getSearchFieldsByEntity: () => {
                        return Promise.resolve({
                            name: searchRankingPoint.HIGH_SEARCH_RANKING,
                        });
                    },
                    buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                        return criteria;
                    },
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-search-bar': true,
                'sw-entity-listing': {
                    props: ['items', 'dataSource'],
                    template: `
                        <div>
                            <template v-for="item in (dataSource || items)">
                                <slot name="actions" v-bind="{ item }"></slot>
                            </template>
                        </div>`,
                },
                'sw-language-switch': true,
                'sw-context-menu-item': true,
                'router-link': true,
                'sw-avatar': true,
                'sw-text-field': true,
                'sw-label': true,
                'sw-checkbox-field': true,
                'sw-pagination': true,
                'sw-bulk-edit-modal': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-sidebar': true,
                'sw-time-ago': true,
            },
        },
    });
}

Contena.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

describe('module/sw-user/page/sw-user-list', () => {
    it('should not be able to create a new user', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const createButton = wrapper.find('.sw-user-list__button-create');

        expect(createButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to create a new user', async () => {
        const wrapper = await createWrapper(['user.creator']);
        await flushPromises();

        const createButton = wrapper.find('.sw-user-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-user-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = await createWrapper(['user.editor']);
        await flushPromises();

        const entityListing = wrapper.find('.sw-user-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-user-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = await createWrapper(['user.deleter']);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-user-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-user-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper(['user.editor']);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-user-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await flushPromises();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not get search ranking fields when term is null', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(0);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not build query score when search ranking field is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });

        await flushPromises();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show empty state when there is not item after filling search term', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await flushPromises();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.mt-empty-state')).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show the manual user', async () => {
        const wrapper = await createWrapper();

        const manualLabel = wrapper.find('.sw-user-list__created-by-admin-label');
        expect(manualLabel).toBeTruthy();
    });

    it('should consider criteria filters via updateCriteria', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const filter = Criteria.equals('foo', 'bar');
        wrapper.vm.updateCriteria([filter]);
        await flushPromises();

        expect(wrapper.vm.filterCriteria).toContainEqual(filter);
    });

    it('should sort the user group column by its name field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const userGroupColumn = wrapper.vm.userColumns.find((column) => column.property === 'group');

        expect(userGroupColumn.dataIndex).toBe('group.name');
    });
});
