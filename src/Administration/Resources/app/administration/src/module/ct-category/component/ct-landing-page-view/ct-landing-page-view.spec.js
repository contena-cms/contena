/**
 * @ct-package discovery
 */

import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import '../../page/ct-category-detail/store';

const landingPageIdMock = 'LANDING_PAGE_MOCK_ID';

async function createWrapper({
    routeName = 'ct.category.landingPageDetail.base',
    routerPush = jest.fn(),
    isLoading = false,
    canEdit = true,
} = {}) {
    Contena.Store.get('swCategoryDetail').$reset();
    Contena.Store.get('swCategoryDetail').landingPage = { id: landingPageIdMock };

    return mount(await wrapTestComponent('ct-landing-page-view', { sync: true }), {
        global: {
            stubs: {
                'ct-card-view': {
                    template: '<div class="ct-card-view"><slot /></div>',
                    props: ['positionIdentifier'],
                },
                'ct-language-info': {
                    template: '<div class="ct-language-info"></div>',
                    props: ['entityDescription'],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: [
                        'defaultItem',
                        'items',
                        'positionIdentifier',
                    ],
                },
                'mt-banner': {
                    template: '<div class="mt-banner"><slot /></div>',
                    props: ['variant'],
                },
                'router-view': {
                    template: '<div class="router-view"></div>',
                },
            },
            mocks: {
                placeholder: (entity, field, fallbackSnippet) => ({ entity, field, fallbackSnippet }),
                $route: { name: routeName },
            },
            provide: {
                [routeLocationKey]: { name: routeName, params: {}, query: {} },
                [routerKey]: { push: routerPush },
                acl: {
                    can: (privilege) => privilege !== 'landing_page.editor' || canEdit,
                },
            },
        },
        props: { isLoading },
    });
}

describe('src/module/ct-category/component/ct-landing-page-view', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('displays the landing page context and position identifiers', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.ct-landing-page-view').props('positionIdentifier')).toBe('ct-landing-page-view');
        expect(wrapper.getComponent('.ct-language-info').props('entityDescription')).toStrictEqual({
            entity: { id: landingPageIdMock },
            field: 'name',
            fallbackSnippet: 'ct-landing-page.general.headlineLandingPages',
        });
        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('positionIdentifier')).toBe('ct-landing-page-view');
    });

    it('provides the general and ContentLayout tabs', async () => {
        const wrapper = await createWrapper();
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items').map((item) => item.name)).toEqual([
            'ct.category.landingPageDetail.base',
            'ct.category.landingPageDetail.layout',
        ]);
    });

    it('navigates to the ContentLayout assignment', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({ routerPush });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const layoutTab = tabs.props('items').find((item) => item.name === 'ct.category.landingPageDetail.layout');

        layoutTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.category.landingPageDetail.layout' });
    });

    it('disables the ContentLayout tab and displays the permission warning without edit privileges', async () => {
        const wrapper = await createWrapper({ canEdit: false });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const layoutTab = tabs.props('items').find((item) => item.name === 'ct.category.landingPageDetail.layout');

        expect(layoutTab.disabled).toBe(true);
        expect(wrapper.getComponent('.ct-landing-page-view__cms-permission-warning').props('variant')).toBe('attention');
    });

    it('hides the tabs while loading', async () => {
        const wrapper = await createWrapper({ isLoading: true });

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });
});
