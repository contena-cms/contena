/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const categoryMock = {
    media: [],
    name: 'Computer parts',
    footerSalesChannels: [],
    navigationSalesChannels: [],
    serviceSalesChannels: [],
    blogAssignmentType: 'blog',
    isNew: () => false,
};

const blogStreamMock = {
    name: 'Very cheap pc parts',
    apiFilter: ['foo', 'bar'],
    invalid: false,
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-detail-blogs', { sync: true }), {
        global: {
            stubs: {
                'router-link': true,
                'sw-container': true,
                'sw-text-field': true,
                'sw-single-select': true,
                'sw-many-to-many-assignment-card': {
                    template: `
                        <div>
                            <slot name="prepend-select"></slot>
                            <slot name="select"></slot>
                            <slot name="data-grid"></slot>
                        </div>`,
                },
                'sw-blog-stream-grid-preview': {
                    template: '<div class="sw-blog-stream-grid-preview"></div>',
                },
                'sw-entity-single-select': {
                    template: '<div class="sw-entity-single-select"></div>',
                },
                'sw-blog-variant-info': true,
                'sw-empty-state': true,
            },
            mocks: {
                placeholder: () => {},
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(blogStreamMock),
                        };
                    },
                },
            },
        },
        props: {
            isLoading: false,
            manualAssignedProductsCount: 0,
        },
    });
}

describe('module/sw-category/view/sw-category-detail-blogs.spec', () => {
    beforeEach(async () => {
        Contena.Store.get('swCategoryDetail').$reset();
        Contena.Store.get('swCategoryDetail').category = categoryMock;
    });

    it('should render stream select when changing the assignment type to stream', async () => {
        const wrapper = await createWrapper();

        await wrapper
            .getComponent('.sw-category-detail-blogs__product-assignment-type-select')
            .vm.$emit('update:value', 'blog_stream');

        // Ensure default select is replaced with stream select inside `select` slot
        expect(wrapper.find('.sw-entity-many-to-many-select').exists()).toBeFalsy();
        expect(wrapper.find('.sw-category-detail-blogs__product-stream-select').exists()).toBe(true);
    });

    it('should render stream preview when changing the assignment type to blog stream', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            category: {
                blogAssignmentType: 'blog_stream',
            },
        });

        // Ensure that the default grid is replaced with blog stream preview grid inside `data-grid` slot
        expect(wrapper.find('.sw-many-to-many-assignment-card__grid').exists()).toBeFalsy();
        expect(wrapper.find('.sw-blog-stream-grid-preview').exists()).toBeTruthy();
    });

    it('should show message when assignment type is blog stream and blogs are manually assigned', async () => {
        const wrapper = await createWrapper();

        await wrapper
            .getComponent('.sw-category-detail-blogs__product-assignment-type-select')
            .vm.$emit('update:value', 'blog_stream');
        await wrapper.setData({
            manualAssignedProductsCount: 5,
        });

        expect(wrapper.find('[role="banner"]').text()).toBe(
            'sw-category.base.blogs.alertManualAssignedProductsOnAssignmentTypeStream',
        );
    });

    it('should have correct default assignment types', async () => {
        const wrapper = await createWrapper();

        const assignmentTypes = wrapper.vm.blogAssignmentTypes;

        expect(assignmentTypes[0].value).toBe('blog');
        expect(assignmentTypes[1].value).toBe('blog_stream');
    });

    it('should try to load blog stream preview when stream id is present', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            manualAssignedProductsCount: 5,
        });

        await wrapper
            .getComponent('.sw-category-detail-blogs__product-stream-select')
            .vm.$emit('update:value', 'some_blog_stream_id');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.blogStreamFilter).toEqual(['foo', 'bar']);
        expect(wrapper.vm.blogStreamInvalid).toBe(false);
    });

    it('should empty the blog stream id when changing the assignment type to blog', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            category: {
                blogStreamId: 'some_blog_stream_id',
            },
        });

        await wrapper
            .getComponent('.sw-category-detail-blogs__product-assignment-type-select')
            .vm.$emit('update:value', 'blog');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.category.blogStreamId).toBeNull();
    });
});
