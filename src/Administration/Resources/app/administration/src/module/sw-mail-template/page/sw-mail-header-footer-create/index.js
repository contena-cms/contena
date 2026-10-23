/**
 * @sw-package after-sales
 */

import template from './sw-mail-header-footer-create.html.twig';

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    methods: {
        createdComponent() {
            if (!Contena.Store.get('context').isSystemDefaultLanguage) {
                Contena.Store.get('context').resetLanguageToDefault();
            }

            this.mailHeaderFooter = this.mailHeaderFooterRepository.create(
                Contena.Context.api,
                this.$route.params.id ?? null,
            );
            this.mailHeaderFooterId = this.mailHeaderFooter.id;

            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.mail.template.detail_head_foot',
                params: { id: this.mailHeaderFooterId },
            });
        },

        onSave() {
            this.isLoading = true;
            this.$super('onSave');
        },
    },
};
