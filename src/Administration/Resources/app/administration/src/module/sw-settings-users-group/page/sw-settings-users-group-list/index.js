import template from './sw-settings-user-group-list.html.twig';

/**
 * @sw-package discovery
 */

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('placeholder'), Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            sortBy: 'name',
            limit: 10,
            userGroups: null,
            sortDirection: 'ASC',
            searchConfigEntity: 'user_group',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },

        userGroupRepository() {
            return this.repositoryFactory.create('user_group');
        },

        showEmptyState() {
            return !this.isLoading && !this.total && this.isValidTerm(this.term);
        },

        allCustomerGroupsCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.allCustomerGroupsCriteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            this.userGroupRepository.search(criteria).then((searchResult) => {
                this.total = searchResult.total;
                this.userGroups = searchResult;
                this.isLoading = false;
            });
        },

        getColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-settings-user-group.list.columnName',
                    inlineEdit: 'string',
                    routerLink: 'sw.settings.user.group.detail',
                    primary: true,
                },
                {
                    property: 'displayGross',
                    label: 'sw-settings-user-group.list.columnDisplayGross',
                    inlineEdit: 'boolean',
                },
            ];
        },

        userGroupCriteriaWithFilter(idsOfSelectedCustomerGroups) {
            const criteria = new Criteria(1, this.limit);

            criteria.addFilter(Criteria.equalsAny('id', idsOfSelectedCustomerGroups));

            return criteria;
        },

        createErrorNotification() {
            return this.createNotificationError({
                message: this.$t('sw-settings-user-group.notification.errorMessageCannotDeleteCustomerGroup'),
            });
        },

        userGroupCanBeDeleted(userGroup) {
            const hasNoCustomers = userGroup.users.length === 0;
            const hasNoSalesChannel = userGroup.channels.length === 0;

            return hasNoCustomers && hasNoSalesChannel;
        },

        deleteCustomerGroup(userGroup) {
            this.$refs.listing.deleteId = null;

            if (!this.userGroupCanBeDeleted(userGroup)) {
                this.createErrorNotification();
            }

            this.userGroupRepository.delete(userGroup.id).then(() => {
                this.$refs.listing.resetSelection();
                this.$refs.listing.doSearch();
            });
        },

        deleteCustomerGroups() {
            const selectedCustomerGroups = Object.values(this.$refs.listing.selection).map((currentProxy) => {
                return currentProxy.id;
            });

            this.userGroupRepository
                .search(this.userGroupCriteriaWithFilter(selectedCustomerGroups))
                .then((response) => {
                    const hasError = response.reduce((accumulator, userGroup) => {
                        if (accumulator) {
                            return accumulator;
                        }

                        accumulator = !this.userGroupCanBeDeleted(userGroup);
                        return accumulator;
                    }, false);

                    if (hasError) {
                        this.createErrorNotification();
                    }

                    this.$refs.listing.deleteItems();
                });
        },

        onContextMenuDelete(userGroup) {
            this.$refs.listing.deleteId = userGroup.id;
        },
    },
};
