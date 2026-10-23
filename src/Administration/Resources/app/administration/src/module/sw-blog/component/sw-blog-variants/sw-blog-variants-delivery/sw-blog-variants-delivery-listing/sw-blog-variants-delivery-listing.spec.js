/**
 * @sw-package inventory
 */
import { shallowMount } from '@vue/test-utils';
import { reactive } from 'vue';

const expandedGroup = { id: 'group-id', expressionForListings: true, representation: 'box' };

async function createWrapper(variantListingConfig) {
    const blog = reactive({ id: 'blog-id', variantListingConfig });

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-variants-delivery-listing', { sync: true }), {
        props: {
            blog,
            selectedGroups: [{ id: 'group-id', translated: { name: 'Size' } }],
        },
        global: {
            stubs: {
                'sw-radio-field': {
                    name: 'sw-radio-field',
                    props: ['value', 'options', 'disabled'],
                    emits: ['update:value'],
                    template: '<div class="sw-radio-field"></div>',
                },
                'mt-checkbox': {
                    name: 'mt-checkbox',
                    props: ['checked', 'disabled', 'label'],
                    emits: ['update:checked'],
                    template: '<div class="mt-checkbox"></div>',
                },
                'sw-entity-single-select': true,
                'sw-blog-variant-info': true,
                'sw-select-result': true,
            },
        },
    });

    return { wrapper, blog };
}

function getRadioFields(wrapper) {
    const [listingModeField, variantModeField] = wrapper.findAllComponents({ name: 'sw-radio-field' });

    return { listingModeField, variantModeField };
}

describe('src/module/sw-blog/component/sw-blog-variants/sw-blog-variants-delivery/sw-blog-variants-delivery-listing', () => {
    it.each([
        [
            'nothing is saved',
            { displayParent: null, configuratorGroupConfig: [] },
            'single',
            false,
        ],
        [
            'nothing is saved for a blog created in the administration',
            { displayParent: null, mainVariantId: null, configuratorGroupConfig: null },
            'single',
            false,
        ],
        [
            'the main blog is shown',
            { displayParent: true, mainVariantId: null, configuratorGroupConfig: null },
            'single',
            true,
        ],
        [
            'a main variant is chosen',
            { displayParent: false, mainVariantId: 'variant-id', configuratorGroupConfig: [] },
            'single',
            false,
        ],
        [
            'a variant is shown without a chosen main variant',
            { displayParent: false, mainVariantId: null, configuratorGroupConfig: null },
            'single',
            false,
        ],
        [
            'no property is enabled for listings',
            {
                displayParent: null,
                mainVariantId: null,
                configuratorGroupConfig: [{ ...expandedGroup, expressionForListings: false }],
            },
            'single',
            false,
        ],
        [
            'a property is enabled for listings',
            { displayParent: null, mainVariantId: null, configuratorGroupConfig: [expandedGroup] },
            'expanded',
            false,
        ],
        [
            'the main blog is shown although a property is enabled for listings',
            { displayParent: true, mainVariantId: null, configuratorGroupConfig: [expandedGroup] },
            'single',
            true,
        ],
    ])(
        'should show the frontend presentation when %s',
        async (_, variantListingConfig, expectedListingMode, expectedMainProduct) => {
            const { wrapper, blog } = await createWrapper(variantListingConfig);
            const { listingModeField, variantModeField } = getRadioFields(wrapper);

            expect(blog.listingMode).toBe(expectedListingMode);
            expect(listingModeField.props('value')).toBe(expectedListingMode);
            expect(variantModeField.props('value')).toBe(expectedMainProduct);
        },
    );

    it('should not change the saved configuration when the tab is opened', async () => {
        const { blog } = await createWrapper({
            displayParent: null,
            mainVariantId: null,
            configuratorGroupConfig: [expandedGroup],
        });

        expect(blog.variantListingConfig).toEqual({
            displayParent: null,
            mainVariantId: null,
            configuratorGroupConfig: [expandedGroup],
        });
    });

    it('should keep the variant choice when the listing mode is switched', async () => {
        const { wrapper, blog } = await createWrapper({
            displayParent: false,
            mainVariantId: null,
            configuratorGroupConfig: [],
        });
        const { listingModeField, variantModeField } = getRadioFields(wrapper);

        listingModeField.vm.$emit('update:value', 'expanded');
        listingModeField.vm.$emit('update:value', 'single');
        await flushPromises();

        expect(blog.listingMode).toBe('single');
        expect(blog.variantListingConfig.displayParent).toBe(false);
        expect(variantModeField.props('value')).toBe(false);
    });

    it('should enable a property for listings', async () => {
        const { wrapper, blog } = await createWrapper({
            displayParent: null,
            mainVariantId: null,
            configuratorGroupConfig: [],
        });

        getRadioFields(wrapper).listingModeField.vm.$emit('update:value', 'expanded');
        await flushPromises();
        wrapper.findComponent({ name: 'mt-checkbox' }).vm.$emit('update:checked', true);
        await flushPromises();

        expect(blog.listingMode).toBe('expanded');
        expect(blog.variantListingConfig.configuratorGroupConfig).toEqual([expandedGroup]);
    });
});
