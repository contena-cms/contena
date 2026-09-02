type LoadingProperty = 'init' | 'blog' | 'customFieldSets' | 'media' | 'seoUrls' | 'contentLayouts';

type BlogDetailBlog = EntitySchema.blog & {
    isNew: () => boolean;
};

const ctBlogDetail = Contena.Store.register({
    id: 'ctBlogDetail',

    state() {
        return {
            blog: {} as BlogDetailBlog,
            creationType: 'post' as string,
            customFieldSets: [] as Array<{ id: string }>,
            loading: {
                init: false,
                blog: false,
                customFieldSets: false,
                media: false,
                seoUrls: false,
                contentLayouts: false,
            },
        };
    },

    getters: {
        isLoading: (state): boolean => Object.values(state.loading).some((loading) => loading),
    },

    actions: {
        setCustomFields(fieldSet: { id: string }): void {
            this.customFieldSets = this.customFieldSets.map((set) => (set.id === fieldSet.id ? fieldSet : set));
        },

        setLoading([
            property,
            loading,
        ]: [
            LoadingProperty,
            boolean,
        ]): boolean {
            if (typeof loading !== 'boolean' || this.loading[property] === undefined) {
                return false;
            }

            this.loading[property] = loading;

            return true;
        },
    },
});

/** @private */
export default ctBlogDetail;

/** @private */
export type CtBlogDetailStore = ReturnType<typeof ctBlogDetail>;
