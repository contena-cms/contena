/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

let blogSortingRepositoryMock;

describe('src/module/sw-setttigs-listing/page/sw-settings-listing-option-create', () => {
    function getProductSortings() {
        return [
            {
                locked: false,
                key: 'asasdsafsdfsdafsdafasdf',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'blog.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                    {
                        field: 'blog.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                    {
                        field: 'my_first_custom_field',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                ],
                label: 'asasdsafsdfsdafsdafasdf',
                createdAt: '2020-08-06T13:06:03.799+00:00',
                updatedAt: null,
                translated: {
                    label: 'asasdsafsdfsdafsdafasdf',
                },
                apiAlias: null,
                id: '481a3502b72c4fd99b693c7998b93e37',
                translations: [],
            },
            {
                locked: false,
                key: 'test',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'blog.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                    {
                        field: 'blog.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                    {
                        field: 'my_first_custom_field',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                ],
                label: 'Test',
                createdAt: '2020-08-06T13:06:03.799+00:00',
                updatedAt: null,
                translated: {
                    label: 'asasdsafsdfsdafsdafasdf',
                },
                apiAlias: null,
                id: '481a3502b72c4fd99b693c7998b93e37',
                translations: [],
            },
        ];
    }

    async function createWrapper() {
        blogSortingRepositoryMock = {
            search: (param) => {
                let response = null;

                getProductSortings().forEach((element) => {
                    if (element[param.filters.field]) {
                        response = element;
                    }
                });

                return Promise.resolve({
                    first: () => {
                        return response;
                    },
                });
            },
            create: () => getProductSortings()[0],
            save: jest.fn(() =>
                Promise.resolve({
                    config: {
                        data: JSON.stringify({
                            id: 'asdfaf',
                        }),
                    },
                }),
            ),
        };

        return mount(
            await wrapTestComponent('sw-settings-listing-option-create', {
                sync: true,
            }),
            {
                global: {
                    mocks: {
                        $router: {},
                    },
                    provide: {
                        repositoryFactory: {
                            create: (repository) => {
                                if (repository === 'blog_sorting') {
                                    return blogSortingRepositoryMock;
                                }
                                return {
                                    search: () => Promise.resolve(),
                                };
                            },
                        },
                        systemConfigApiService: {},
                    },
                    stubs: {
                        'sw-page': {
                            template: '<div></div>',
                        },
                        'sw-language-switch': true,
                        'sw-settings-listing-option-general-info': true,
                        'sw-settings-listing-option-criteria-grid': true,
                        'sw-settings-listing-delete-modal': true,
                    },
                },
            },
        );
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should create a blog sorting entity', async () => {
        const blogSortingEntity = wrapper.vm.blogSortingEntity;

        await expect(blogSortingEntity).toEqual({
            active: false,
            apiAlias: null,
            createdAt: '2020-08-06T13:06:03.799+00:00',
            fields: [],
            id: '481a3502b72c4fd99b693c7998b93e37',
            key: 'asasdsafsdfsdafsdafasdf',
            label: 'asasdsafsdfsdafsdafasdf',
            locked: false,
            position: 1,
            priority: 1,
            translated: { label: 'asasdsafsdfsdafsdafasdf' },
            translations: [],
            updatedAt: null,
        });
    });

    it('should throw an success message when saving', async () => {
        wrapper.vm.$router.push = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.$router.push).toHaveBeenCalled();
    });

    it("should throw an error message when the blog sorting entity couldn't be saved", async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should handle the "KeyAlreadyExists" case', async () => {
        wrapper.vm.blogSortingEntity.key = 'existingKey';
        const resolvedValue = [{}];
        wrapper.vm.blogSortingRepository.search = jest.fn().mockResolvedValue(resolvedValue);
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: wrapper.vm.$t('sw-settings-listing.base.notification.saveError', {
                sortingOptionName: wrapper.vm.blogSortingEntity.label,
            }),
        });
    });

    it('should display the entity name for the smart bar heading', async () => {
        wrapper.vm.blogSortingEntity.label = 'label';

        expect(wrapper.vm.smartBarHeading).toBe('label');
    });

    it('should display the fallback snippet for the smart bar heading', async () => {
        wrapper.vm.blogSortingEntity.label = '';

        expect(wrapper.vm.smartBarHeading).toBe('sw-settings-listing.create.smartBarTitle');
    });

    it('should transform customField fields onSave', async () => {
        wrapper.vm.$router.push = jest.fn();

        blogSortingRepositoryMock.save = jest.fn().mockResolvedValue({
            config: {
                data: JSON.stringify([]),
            },
        });
        wrapper.vm.transformCustomFieldCriterias = jest.fn();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(blogSortingRepositoryMock.save).toHaveBeenCalled();
        expect(wrapper.vm.transformCustomFieldCriterias).toHaveBeenCalled();
    });
});
