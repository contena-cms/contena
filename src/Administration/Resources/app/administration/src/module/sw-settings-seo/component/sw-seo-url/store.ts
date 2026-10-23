/**
 * @sw-package inventory
 */
const swSeoUrlStore = Contena.Store.register({
    id: 'swSeoUrl',

    state() {
        return {
            channelCollection: null as EntityCollection<'channel'> | null,
            seoUrlCollection: null as EntityCollection<'seo_url'> | null,
            originalSeoUrls: [] as Entity<'seo_url'>[],
            defaultSeoUrl: null as Entity<'seo_url'> | null,
            currentSeoUrl: null as Entity<'seo_url'> | null,
        };
    },

    getters: {
        newOrModifiedUrls() {
            const seoUrls: Entity<'seo_url'>[] = [];

            this.seoUrlCollection?.forEach((seoUrl) => {
                if (seoUrl.seoPathInfo === null) {
                    return;
                }

                const originalSeoUrl = this.originalSeoUrls.find((url) => url.id === seoUrl.id);

                if (originalSeoUrl && originalSeoUrl.seoPathInfo === seoUrl.seoPathInfo) {
                    return;
                }

                if (!originalSeoUrl && !seoUrl.seoPathInfo) {
                    return;
                }

                seoUrls.push(seoUrl);
            });

            return seoUrls;
        },
    },
});

/**
 * @private
 */
export type SwSeoUrlStore = ReturnType<typeof swSeoUrlStore>;

/**
 * @private
 */
export default swSeoUrlStore;
