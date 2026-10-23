/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-create.html.twig';

const { Context } = Contena;
const utils = Contena.Utils;

const insertIdIntoRoute = (to, from, next) => {
    if (to.name.includes('sw.sales.channel.create') && !to.params.id) {
        to.params.id = utils.createId();
    }

    next();
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    beforeRouteEnter: insertIdIntoRoute,

    beforeRouteUpdate: insertIdIntoRoute,

    inject: ['systemConfigApiService'],

    computed: {
        allowSaving() {
            return this.acl.can('channel.creator');
        },
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.typeId) {
                return;
            }

            if (!Contena.Store.get('context').isSystemDefaultLanguage) {
                Contena.Store.get('context').resetLanguageToDefault();
            }

            this.isLoading = true;
            this.channel = this.channelRepository.create();
            this.channel.typeId = this.$route.params.typeId;
            this.channel.active = false;
            this.channel.measurementUnits = this.createEmptyMeasurementUnits();

            // Set default language from admin context
            const defaultLanguageId = Contena.Store.get('context').api.languageId;
            this.channel.languageId = defaultLanguageId;
            this.ensureDefaultLanguageInCollection(defaultLanguageId);

            this.setMeasurementUnits()
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-sales-channel.detail.messageMeasurementUnitsSetError'),
                    });
                })
                .finally(() => {
                    this.$super('createdComponent');
                    this.isLoading = false;
                });
        },

        createEmptyMeasurementUnits() {
            return {
                system: null,
                units: {
                    length: null,
                    weight: null,
                },
            };
        },

        async setMeasurementUnits() {
            const measurementUnits = await this.getMeasurementUnits();

            this.channel.measurementUnits = {
                system: measurementUnits['core.measurementUnits.system'],
                units: {
                    length: measurementUnits['core.measurementUnits.length'],
                    weight: measurementUnits['core.measurementUnits.weight'],
                },
            };
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.sales.channel.detail',
                params: { id: this.channel.id },
            });
        },

        onSave() {
            this.$super('onSave');
        },

        getMeasurementUnits() {
            return this.systemConfigApiService.getValues('core.measurementUnits');
        },

        ensureDefaultLanguageInCollection(languageId) {
            if (!languageId || !this.channel?.languages) {
                return;
            }

            if (this.channel.languages.has(languageId)) {
                return;
            }

            const languageRepository = this.repositoryFactory.create('language');
            languageRepository.get(languageId, Context.api).then((language) => {
                if (!language || this.channel.languages.has(languageId)) {
                    return;
                }

                if (typeof this.channel.languages.add === 'function') {
                    this.channel.languages.add(language);
                    return;
                }

                this.channel.languages = this.channel.languages.concat([language]);
            });
        },
    },
};
