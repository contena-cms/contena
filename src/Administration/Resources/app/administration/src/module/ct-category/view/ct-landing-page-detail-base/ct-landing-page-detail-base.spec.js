/**
 * @ct-package buyers-experience
 */
import { mount } from '@vue/test-utils';
import '../../page/ct-category-detail/store';

async function createWrapper({
    landingPage = {
        id: 'landing-page-id',
    },
    customFieldSets = [],
} = {}) {
    Contena.Store.get('swCategoryDetail').$reset();
    Contena.Store.get('swCategoryDetail').category = {
        media: [],
        name: 'Computer parts',
        footerChannels: [],
        navigationChannels: [],
        serviceChannels: [],
        productAssignmentType: 'product',
        isNew: () => false,
    };
    Contena.Store.get('swCategoryDetail').landingPage = landingPage;
    Contena.Store.get('swCategoryDetail').customFieldSets = customFieldSets;

    return mount(await wrapTestComponent('ct-landing-page-detail-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'ct-container': {
                    template: '<div class="ct-container"><slot></slot></div>',
                },
                'ct-text-field': {
                    template:
                        '<input class="ct-text-field" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'ct-entity-tag-select': {
                    template: '<input type="select" class="ct-entity-tag-select"/>',
                    props: ['disabled'],
                },
                'ct-entity-multi-select': true,
                'mt-banner': true,
                'mt-textarea': true,
                'ct-custom-field-set-renderer': {
                    name: 'ct-custom-field-set-renderer',
                    props: ['disabled'],
                    template: '<div class="ct-custom-field-set-renderer"></div>',
                },
            },
            computed: {
                landingPage() {
                    return Contena.Store.get('swCategoryDetail').landingPage;
                },
            },
        },
        props: {
            isLoading: false,
        },
    });
}

describe('module/ct-category/view/ct-landing-page-detail-base.spec', () => {
    it('should disable the custom field renderer without landing page edit permissions', async () => {
        global.activeAclRoles = ['landing_page.viewer'];

        const wrapper = await createWrapper({ customFieldSets: [{}] });

        expect(wrapper.getComponent('.ct-custom-field-set-renderer').props('disabled')).toBe(true);
    });
});
