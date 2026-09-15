import { mount } from '@vue/test-utils';
import 'src/app/component/structure/ct-search-bar-item';
import 'src/app/component/base/ct-highlight-text';
import RecentlySearchService from 'src/app/service/recently-search.service';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import { routerKey } from 'vue-router';
import { createI18n } from 'vue-i18n';

const searchTypeServiceTypes = {
    media: {
        entityName: 'media',
        entityService: 'mediaService',
        placeholderSnippet: 'ct-media.general.placeholderSearchBar',
        listingRoute: 'ct.media.index',
    },
};

describe('src/app/component/structure/ct-search-bar-item', () => {
    /** @type Wrapper */
    let wrapper;
    let recentlySearchService;
    let spyRecentlySearchServiceAdd;

    async function createWrapper(props, shortcut) {
        const component = await wrapTestComponent('ct-search-bar-item', { sync: true });
        recentlySearchService = new RecentlySearchService();
        spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');

        return mount(component, {
            global: {
                stubs: {
                    'ct-highlight-text': true,
                    'ct-shortcut-overview-item': true,
                    'router-link': {
                        emits: ['click'],
                        template: '<div class="ct-router-link" @click="$emit(\'click\', $event)"><slot></slot></div>',
                        props: ['to'],
                    },
                },
                plugins: shortcut
                    ? [
                          createI18n({
                              legacy: false,
                              locale: 'en',
                              messages: {
                                  en: {
                                      global: { 'ct-search-bar-item': { shortcuts: { [shortcut.name]: shortcut.value } } },
                                  },
                              },
                          }),
                      ]
                    : undefined,
                provide: {
                    [routerKey]: {
                        push: jest.fn(),
                    },
                    recentlySearchService,
                    searchTypeService: {
                        getTypes: () => searchTypeServiceTypes,
                    },
                    searchBarOnMouseOver: jest.fn(),
                    searchBarRegisterActiveItemIndexSelectHandler: jest.fn(),
                    searchBarUnregisterActiveItemIndexSelectHandler: jest.fn(),
                    searchBarRegisterKeyupEnterHandler: jest.fn(),
                    searchBarUnregisterKeyupEnterHandler: jest.fn(),
                },
            },
            props,
        });
    }

    beforeEach(async () => {
        Contena.Store.get('session').setCurrentUser({
            id: 'userId',
        });
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should add clicked search result into recently search stack', async () => {
        wrapper = await createWrapper({
            entityIconName: 'regular-image',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'media',
            item: {
                id: 'mediaId',
                fileName: 'example',
                fileExtension: 'png',
            },
        });

        wrapper.vm.onClickSearchResult('media', 'mediaId');

        expect(spyRecentlySearchServiceAdd).toHaveBeenCalledTimes(1);
        expect(spyRecentlySearchServiceAdd).toHaveBeenCalledWith('userId', 'media', 'mediaId', {});
    });

    it('should return filters from filter registry', async () => {
        wrapper = await createWrapper({
            entityIconName: 'regular-image',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'media',
            item: {
                id: 'mediaId',
                fileName: 'example',
                fileExtension: 'png',
            },
        });

        expect(wrapper.vm.mediaNameFilter).toEqual(expect.any(Function));
    });

    describe('module icon colors', () => {
        const moduleItem = {
            entityIconName: 'regular-file-text',
            entityIconColor: 'var(--ct-color-module-green-default)',
            column: 1,
            index: 1,
            type: 'module',
            item: {
                name: 'ct-blog',
                color: 'var(--ct-color-module-green-default)',
                icon: 'regular-file-text',
                route: 'ct.blog.index',
            },
        };

        afterEach(() => {
            useModuleIconColors().enabled.value = false;
        });

        it('should use the neutral icon color by default', async () => {
            wrapper = await createWrapper(moduleItem);

            expect(wrapper.vm.iconColor).toBe('var(--color-icon-primary-default)');
        });

        it('should use the module color when the preference is enabled', async () => {
            useModuleIconColors().enabled.value = true;
            wrapper = await createWrapper(moduleItem);

            expect(wrapper.vm.iconColor).toBe('var(--ct-color-module-green-default)');
        });

        it('should fall back to the entity icon color for entity results', async () => {
            useModuleIconColors().enabled.value = true;
            wrapper = await createWrapper({
                ...moduleItem,
                type: 'blog',
                item: { id: 'blogId', title: 'Example' },
            });

            expect(wrapper.vm.iconColor).toBe('var(--ct-color-module-green-default)');
        });
    });

    describe('shortcut', () => {
        it('should render a resolved module shortcut', async () => {
            wrapper = await createWrapper(
                {
                    entityIconName: 'regular-file-text',
                    entityIconColor: 'blue',
                    column: 0,
                    index: 0,
                    type: 'module',
                    item: { name: 'blog', label: 'Blog', route: 'ct.blog.index' },
                },
                { name: 'blog', value: 'G B' },
            );

            expect(wrapper.vm.shortcut).toBe('G B');
            expect(wrapper.find('ct-shortcut-overview-item-stub').exists()).toBe(true);
        });

        it('should hide the upstream no-shortcut placeholder', async () => {
            wrapper = await createWrapper(
                {
                    entityIconName: 'regular-file-text',
                    entityIconColor: 'blue',
                    column: 0,
                    index: 0,
                    type: 'module',
                    item: { name: 'category', label: 'Category', action: true, route: 'ct.category.index' },
                },
                { name: 'category', value: '&nbsp; ' },
            );

            expect(wrapper.vm.shortcut).toBe(false);
            expect(wrapper.find('ct-shortcut-overview-item-stub').exists()).toBe(false);
        });
    });
});
