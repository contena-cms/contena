/**
 * @ct-package discovery
 */
import { mount } from '@vue/test-utils';
import '../../page/ct-category-detail/store';

const categoryMock = {
    media: [],
    name: 'Computer parts',
    footerChannels: [],
    navigationChannels: [],
    serviceChannels: [],
    productAssignmentType: 'product',
    isNew: () => false,
};

async function createWrapper({ customFieldSets = [] } = {}) {
    Contena.Store.get('ctCategoryDetail').$reset();
    Contena.Store.get('ctCategoryDetail').category = categoryMock;
    Contena.Store.get('ctCategoryDetail').customFieldSets = customFieldSets;

    return mount(await wrapTestComponent('ct-category-detail-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'ct-container': {
                    template: '<div class="ct-container"><slot></slot></div>',
                },
                'ct-single-select': {
                    template: '<input type="select" class="ct-single-select"></input>',
                    props: ['disabled'],
                },
                'ct-entity-tag-select': {
                    template: '<input type="select" class="ct-entity-tag-select"></input>',
                    props: ['disabled'],
                },
                'ct-category-detail-menu': {
                    template: '<div class="ct-category-detail-menu"></div>',
                },
                'ct-category-entry-point-card': true,
                'ct-category-link-settings': true,
                'ct-custom-field-set-renderer': {
                    name: 'ct-custom-field-set-renderer',
                    props: ['disabled'],
                    template: '<div class="ct-custom-field-set-renderer"></div>',
                },
            },
        },
        props: {
            isLoading: false,
            manualAssignedProductsCount: 0,
        },
    });
}

describe('module/ct-category/view/ct-category-detail-base.spec', () => {
    it('should disable all interactive elements', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('input').forEach((element) => {
            expect(element.props('disabled')).toBe(true);
        });
    });

    it('should enable all interactive elements', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('input').forEach((element) => {
            expect(element.props('disabled')).toBe(false);
        });
    });

    it('should disable the custom field renderer without category edit permissions', async () => {
        global.activeAclRoles = ['category.viewer'];

        const wrapper = await createWrapper({ customFieldSets: [{}] });

        expect(wrapper.getComponent('.ct-custom-field-set-renderer').props('disabled')).toBe(true);
    });
});
