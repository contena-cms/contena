import template from './sw-bulk-edit-user.html.twig';
import './sw-bulk-edit-user.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;
const { types } = Contena.Utils;
const { chunk } = Contena.Utils.array;
const { cloneDeep } = Contena.Utils.object;

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['bulkEditApiFactory', 'repositoryFactory', 'customFieldDataProviderService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isLoadedData: false,
            isSaveSuccessful: false,
            bulkEditData: {},
            customFieldSets: [],
            processStatus: '',
            user: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        selectedIds() {
            return Contena.Store.get('swBulkEdit').selectedIds;
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'user'));

            return criteria;
        },

        hasChanges() {
            const customFieldsValue = this.bulkEditData.customFields?.value;
            const hasFieldsChanged = Object.values(this.bulkEditData).some((field) => field.isChanged);
            const hasCustomFieldsChanged = !types.isEmpty(customFieldsValue) && Object.keys(customFieldsValue).length > 0;

            return hasFieldsChanged || hasCustomFieldsChanged;
        },

        actionsRequestGroup() {
            return [
                {
                    value: 'accept',
                    label: this.$t('sw-bulk-edit.user.account.userGroupRequest.options.accept'),
                },
                {
                    value: 'decline',
                    label: this.$t('sw-bulk-edit.user.account.userGroupRequest.options.decline'),
                },
            ];
        },

        accountFormFields() {
            return [
                {
                    name: 'groupId',
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'user_group',
                        changeLabel: this.$t('sw-bulk-edit.user.account.userGroup.label'),
                        placeholder: this.$t('sw-bulk-edit.user.account.userGroup.placeholder'),
                    },
                },
                {
                    name: 'active',
                    type: 'bool',
                    config: {
                        type: 'switch',
                        changeLabel: this.$t('sw-bulk-edit.user.account.status.label'),
                    },
                },
                {
                    name: 'languageId',
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'language',
                        changeLabel: this.$t('sw-bulk-edit.user.account.language.label'),
                        placeholder: this.$t('sw-bulk-edit.user.account.language.placeholder'),
                    },
                },
                {
                    name: 'requestedCustomerGroupId',
                    labelHelpText: this.$t('sw-bulk-edit.user.account.userGroupRequest.helpText'),
                    config: {
                        componentName: 'sw-single-select',
                        entity: 'user_group',
                        changeLabel: this.$t('sw-bulk-edit.user.account.userGroupRequest.label'),
                        placeholder: this.$t('sw-bulk-edit.user.account.userGroupRequest.placeholder'),
                        options: this.actionsRequestGroup,
                    },
                },
            ];
        },

        tagsFormFields() {
            return [
                {
                    name: 'tags',
                    config: {
                        componentName: 'sw-entity-tag-select',
                        entityCollection: this.user.tags,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$t('sw-bulk-edit.order.tags.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.order.tags.placeholder'),
                    },
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setRouteMetaModule();
            if (!Contena.Store.get('context').isSystemDefaultLanguage) {
                Contena.Store.get('context').resetLanguageToDefault();
            }

            this.isLoading = true;

            this.user = this.userRepository.create(Contena.Context.api);

            this.loadCustomFieldSets()
                .then(() => {
                    this.loadBulkEditData();
                    this.isLoadedData = true;
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: error,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        setRouteMetaModule() {
            if (!this.$route.meta.$module) {
                this.$route.meta.$module = {};
            }

            this.$route.meta.$module.color = 'var(--sw-color-module-orange-default)';
            this.$route.meta.$module.icon = 'regular-users';
        },

        defineBulkEditData(name, value = null, type = 'overwrite', isChanged = false) {
            if (this.bulkEditData[name]) {
                return;
            }

            this.bulkEditData[name] = {
                isChanged: isChanged,
                type: type,
                value: value,
            };
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [this.accountFormFields, this.tagsFormFields];

            bulkEditFormGroups.forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.defineBulkEditData(bulkEditForm.name);
                });
            });

            this.bulkEditData.customFields = {
                type: 'overwrite',
                value: null,
            };
        },

        loadCustomFieldSets() {
            return this.customFieldDataProviderService.getCustomFieldSets('user', false, null).then((res) => {
                this.customFieldSets = res;
            });
        },

        onCustomFieldsChange(value) {
            if (Object.keys(value).length <= 0) {
                this.bulkEditData = this.bulkEditData.filter((change) => change.field !== 'customFields');
                return;
            }

            this.bulkEditData.customFields.value = value;
        },

        onProcessData() {
            const data = {
                requestData: [],
                syncData: [],
            };

            Object.keys(this.bulkEditData).forEach((key) => {
                const bulkEditField = cloneDeep(this.bulkEditData[key]);

                let bulkEditValue = this.user[key];

                if (key === 'active' && !bulkEditValue) {
                    bulkEditValue = false;
                }

                if (key === 'customFields') {
                    bulkEditValue = bulkEditField.value;
                }

                const change = {
                    field: key,
                    type: bulkEditField.type,
                    value: bulkEditValue,
                };

                if (bulkEditField.isChanged || (key === 'customFields' && bulkEditField.value)) {
                    if (key === 'requestedCustomerGroupId') {
                        data.requestData.push(change);
                    } else {
                        data.syncData.push(change);
                    }
                }
            });

            return data;
        },

        openModal() {
            this.$router.push({ name: 'sw.bulk.edit.user.save' });
        },

        async onSave() {
            this.isLoading = true;
            const { requestData, syncData } = this.onProcessData();
            const bulkEditCustomerHandler = this.bulkEditApiFactory.getHandler('user');
            const payloadChunks = chunk(this.selectedIds, 50);
            const requests = [];

            if (requestData.length) {
                requests.push(bulkEditCustomerHandler.bulkEditRequestedGroup(this.selectedIds, requestData));
            }

            payloadChunks.forEach((payload) => {
                if (syncData.length) {
                    requests.push(this.bulkEditApiFactory.getHandler('user').bulkEdit(payload, syncData));
                }
            });

            return Promise.all(requests)
                .then(() => {
                    this.processStatus = 'success';
                })
                .catch((e) => {
                    console.error(e);
                    this.processStatus = 'fail';
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        closeModal() {
            this.$router.push({ name: 'sw.bulk.edit.user' });
        },

        onChangeLanguage(languageId) {
            Contena.Store.get('context').setApiLanguageId(languageId);
        },
    },
};
