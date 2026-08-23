import { mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import component from './index';

interface BlogCategoryFormVm {
    blog: Entity<'blog'>;
    displayAdvancedVisibility: () => void;
    closeAdvancedVisibility: () => void;
    updateVisibilities: (visibilities: EntityCollection<'blog_visibility'>) => void;
    updateTags: (tags: EntityCollection<'tag'>) => void;
    updateSearchKeywords: (keywords: string[]) => void;
}

function getCollection<EntityName extends keyof EntitySchema.Entities>(
    entity: EntityName,
    entries: Entity<EntityName>[] = [],
): EntityCollection<EntityName> {
    return new EntityCollection(
        `/${entity.replaceAll('_', '-')}`,
        entity,
        Contena.Context.api,
        null,
        entries,
        entries.length,
        null,
    );
}

function createWrapper() {
    const store = Contena.Store.get('swBlogDetail');
    store.$reset();
    store.blog = {
        id: 'blog-1',
        visibilities: getCollection('blog_visibility'),
        categories: getCollection('category'),
        tags: getCollection('tag'),
        customSearchKeywords: [],
        getEntityName: () => 'blog',
    } as unknown as Entity<'blog'> & { isNew: () => boolean };

    return mount(component, {
        props: { allowEdit: true },
        global: {
            stubs: {
                'ct-blog-visibility-select': true,
                'ct-category-tree-field': true,
                'ct-entity-tag-select': true,
                'ct-multi-tag-select': true,
                'ct-container': true,
                'ct-modal': true,
                'ct-blog-visibility-detail': true,
                'mt-link': true,
            },
        },
    }) as unknown as VueWrapper<BlogCategoryFormVm>;
}

describe('module/ct-blog/component/ct-blog-category-form', () => {
    it('opens and closes the advanced visibility detail', async () => {
        const wrapper = createWrapper();

        wrapper.vm.displayAdvancedVisibility();
        await wrapper.vm.$nextTick();
        expect(wrapper.findComponent('ct-modal-stub').exists()).toBe(true);

        wrapper.vm.closeAdvancedVisibility();
        await wrapper.vm.$nextTick();
        expect(wrapper.findComponent('ct-modal-stub').exists()).toBe(false);
    });

    it('updates visibility, tag and search keyword values on the Blog', () => {
        const wrapper = createWrapper();
        const visibilities = getCollection('blog_visibility', [
            { id: 'visibility-1', channelId: 'channel-1' } as Entity<'blog_visibility'>,
        ]);
        const tags = getCollection('tag', [{ id: 'tag-1', name: 'Editorial' } as Entity<'tag'>]);

        wrapper.vm.updateVisibilities(visibilities);
        wrapper.vm.updateTags(tags);
        wrapper.vm.updateSearchKeywords([
            'editorial',
            'guide',
        ]);

        expect([...(wrapper.vm.blog.visibilities?.getIds() ?? [])]).toEqual(['visibility-1']);
        expect([...(wrapper.vm.blog.tags?.getIds() ?? [])]).toEqual(['tag-1']);
        expect(wrapper.vm.blog.customSearchKeywords).toEqual([
            'editorial',
            'guide',
        ]);
    });
});
