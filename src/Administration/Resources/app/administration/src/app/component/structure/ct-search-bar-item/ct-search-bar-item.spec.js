import { mount } from '@vue/test-utils';
import 'src/app/component/structure/ct-search-bar-item';
import 'src/app/component/base/ct-highlight-text';
import RecentlySearchService from 'src/app/service/recently-search.service';
import { routerKey } from 'vue-router';

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

    async function createWrapper(props) {
        const component = await wrapTestComponent('ct-search-bar-item', { sync: true });
        recentlySearchService = new RecentlySearchService();
        spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');

        return mount(component, {
            global: {
                stubs: {
                    'ct-highlight-text': true,
                    'router-link': {
                        emits: ['click'],
                        template: '<div class="ct-router-link" @click="$emit(\'click\', $event)"><slot></slot></div>',
                        props: ['to'],
                    },
                },
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
});
