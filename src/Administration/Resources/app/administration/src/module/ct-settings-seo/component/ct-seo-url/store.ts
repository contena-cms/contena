const ctSeoUrlStore = Contena.Store.register({
    id: 'ctSeoUrl',

    state() {
        return {
            channelCollection: null as EntitySchema.EntityCollection<'channel'> | null,
            seoUrlCollection: null as EntitySchema.EntityCollection<'seo_url'> | null,
            originalSeoUrls: [] as EntitySchema.Entities['seo_url'][],
            defaultSeoUrl: null as EntitySchema.Entities['seo_url'] | null,
            currentSeoUrl: null as EntitySchema.Entities['seo_url'] | null,
        };
    },

    getters: {
        newOrModifiedUrls() {
            const seoUrls: EntitySchema.Entities['seo_url'][] = [];

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
export type CtSeoUrlStore = ReturnType<typeof ctSeoUrlStore>;

/**
 * @private
 */
export default ctSeoUrlStore;
