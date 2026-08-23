import { shallowMount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import component from './index';

interface BlogDetailSeoVm {
    blog: Entity<'blog'> & {
        mainCategories: EntityCollection<'blog_main_category'>;
    };
    onAddMainCategory: (mainCategory: Entity<'blog_main_category'>) => void;
    onRemoveMainCategory: (mainCategory: Entity<'blog_main_category'>) => void;
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
        id: 'blog1',
        versionId: 'version1',
        categories: getCollection('category', [{ id: 'category1' } as Entity<'category'>]),
        mainCategories: getCollection('blog_main_category'),
        seoUrls: getCollection('seo_url'),
        getEntityName: () => 'blog',
    } as unknown as Entity<'blog'> & { isNew: () => boolean };

    return shallowMount(component, {
        global: {
            provide: {
                acl: { can: () => true },
            },
            stubs: {
                'ct-blog-seo-form': true,
                'ct-seo-url': true,
                'ct-seo-main-category': true,
                'ct-skeleton': true,
                'mt-card': true,
            },
        },
    }) as unknown as VueWrapper<BlogDetailSeoVm>;
}

describe('module/ct-blog/view/ct-blog-detail-seo', () => {
    it('should add a Blog main category with the Blog identity', () => {
        const wrapper = createWrapper();
        const mainCategory = {
            id: 'mainCategory1',
            channelId: 'channel1',
            categoryId: 'category1',
        } as Entity<'blog_main_category'>;

        wrapper.vm.onAddMainCategory(mainCategory);

        expect(wrapper.vm.blog.mainCategories).toHaveLength(1);
        expect(mainCategory.blogId).toBe('blog1');
        expect(mainCategory.blogVersionId).toBe('version1');
    });

    it('should remove a Blog main category', () => {
        const wrapper = createWrapper();
        const mainCategory = {
            id: 'mainCategory1',
            blogId: 'blog1',
            blogVersionId: 'version1',
            channelId: 'channel1',
            categoryId: 'category1',
        } as Entity<'blog_main_category'>;
        wrapper.vm.blog.mainCategories.add(mainCategory);

        wrapper.vm.onRemoveMainCategory(mainCategory);

        expect(wrapper.vm.blog.mainCategories).toHaveLength(0);
    });
});
