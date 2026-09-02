/**
 * @ct-package discovery
 */

import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import ContenaError from 'src/core/data/ContenaError';
import '../../page/ct-category-detail/store';

const categoryIdMock = 'CATEGORY_MOCK_ID';

async function createWrapper(
    categoryType = 'page',
    { routeName = 'ct.category.detail.base', routerPush = jest.fn(), isLoading = false } = {},
) {
    Contena.Store.get('ctCategoryDetail').$reset();
    Contena.Store.get('ctCategoryDetail').category = { id: categoryIdMock };
    Contena.Store.get('ctCategoryDetail').isCategoryColumn = true;

    return mount(await wrapTestComponent('ct-category-view', { sync: true }), {
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
            },
        },
        props: {
            isLoading,
            type: categoryType,
        },
    });
}

describe('src/module/ct-category/component/ct-category-view', () => {
    afterEach(() => {
        Contena.Store.get('error').resetApiErrors();
        jest.restoreAllMocks();
    });

    it('displays the category context and position identifiers', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.ct-category-view').props('positionIdentifier')).toBe('ct-category-view');
        expect(wrapper.getComponent('.ct-language-info').props('entityDescription')).toStrictEqual({
            entity: { id: categoryIdMock },
            field: 'name',
            fallbackSnippet: 'ct-category.general.headlineCategories',
        });
        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('positionIdentifier')).toBe('ct-category-view');
    });

    it.each([
        [
            'page',
            [
                'ct.category.detail.base',
                'ct.category.detail.layout',
                'ct.category.detail.seo',
            ],
        ],
        [
            'folder',
            ['ct.category.detail.base'],
        ],
        [
            'link',
            ['ct.category.detail.base'],
        ],
        [
            'custom_entity',
            [
                'ct.category.detail.base',
                'ct.category.detail.layout',
                'ct.category.detail.seo',
            ],
        ],
    ])('provides the expected tabs for the `%s` category type', async (categoryType, expectedTabNames) => {
        const wrapper = await createWrapper(categoryType);
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items').map((item) => item.name)).toEqual(expectedTabNames);
    });

    it('navigates to the ContentLayout assignment', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper('page', { routerPush });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const layoutTab = tabs.props('items').find((item) => item.name === 'ct.category.detail.layout');

        layoutTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.category.detail.layout' });
    });

    it('passes the general tab error state to Meteor tabs', async () => {
        Contena.Store.get('error').addApiError({
            expression: `category.${categoryIdMock}.name`,
            error: new ContenaError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        const wrapper = await createWrapper();
        const generalTab = wrapper.getComponent({ name: 'mt-tabs' }).props('items')[0];

        expect(generalTab).toEqual(expect.objectContaining({ hasError: true }));
    });

    it('hides the tabs while loading', async () => {
        const wrapper = await createWrapper('page', { isLoading: true });

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });
});
