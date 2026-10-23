import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from '../../../../core/data/repository.data';

import template from './sw-order-user-grid.html.twig';
import './sw-order-user-grid.scss';

import type { Cart } from '../../order.types';

/**
 * @sw-package checkout
 */

const { Component, Store, Mixin, Context } = Contena;
const { Criteria } = Contena.Data;

interface GridColumn {
    property: string;
    dataIndex?: string;
    label: string;
    primary?: boolean;
}

interface UserFilterRef {
    term: string;
}

type ApiErrorResponse = {
    response?: {
        data?: {
            errors?: Array<{
                code?: string;
            }>;
        };
    };
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data(): {
        users: EntityCollection<'user'> | null;
        isLoading: boolean;
        isSwitchingCustomer: boolean;
        showNewCustomerModal: boolean;
        user: Entity<'user'> | null;
        disableRouteParams: boolean;
        showSalesChannelSelectModal: boolean;
        showCustomerChangesModal: boolean;
        channelIds: string[];
        userDraft: Entity<'user'> | null;
    } {
        return {
            users: null,
            isLoading: false,
            isSwitchingCustomer: false,
            showNewCustomerModal: false,
            user: null,
            userDraft: null,
            disableRouteParams: true,
            showSalesChannelSelectModal: false,
            showCustomerChangesModal: false,
            channelIds: [],
        };
    },

    computed: {
        userData(): Entity<'user'> | null {
            return Store.get('swOrder').user;
        },

        userRepository(): RepositoryType<'user'> {
            return this.repositoryFactory.create('user');
        },

        userCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('channel');
            criteria.addAssociation('boundSalesChannel');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        userCriterion(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('channel.languages')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags')
                .addAssociation('boundSalesChannel');

            return criteria;
        },

        userColumns(): GridColumn[] {
            return [
                {
                    property: 'select',
                    label: '',
                },
                {
                    property: 'firstName',
                    dataIndex: 'lastName,firstName',
                    label: this.$t('sw-order.initialModal.userGrid.columnCustomerName'),
                    primary: true,
                },
                {
                    property: 'userNumber',
                    label: this.$t('sw-order.initialModal.userGrid.columnCustomerNumber'),
                },
                {
                    property: 'channel',
                    label: this.$t('sw-order.initialModal.userGrid.columnSalesChannel'),
                },
                {
                    property: 'email',
                    label: this.$t('sw-order.initialModal.userGrid.columnEmailAddress'),
                },
            ];
        },

        showEmptyState(): boolean {
            return !this.total && !this.isLoading;
        },

        emptyTitle(): string {
            if (!this.term) {
                return this.$t('sw-user.list.messageEmpty');
            }

            return this.$t('sw-order.initialModal.userGrid.textEmptySearch', { name: this.term }, 0);
        },

        cart(): Cart {
            return Store.get('swOrder').cart;
        },

        /** @deprecated tag:v6.8.0 - Will be removed, use Contena.Filter.getByName('asset') instead. */
        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        channelRepository(): RepositoryType<'channel'> {
            return this.repositoryFactory.create('channel');
        },

        channelCriteria(): CriteriaType {
            const criteria = new Criteria();
            criteria.addAssociation('languages');
            criteria.addFilter(Criteria.equals('active', true));

            if (this.user?.boundSalesChannelId) {
                criteria.addFilter(Criteria.equals('id', this.user.boundSalesChannelId));
            }

            return criteria;
        },

        isSelectSalesChannelDisabled(): boolean {
            if (!this.user?.channelId) {
                return true;
            }

            return !this.channelIds.includes(this.user.channelId);
        },
    },

    mounted() {
        void this.mountedComponent();
    },

    methods: {
        async mountedComponent(): Promise<void> {
            this.channelIds = await this.loadSalesChannel();

            if (!this.userData) {
                return;
            }

            const userNumber = this.userData.userNumber ?? '';

            const userFilter = this.$refs.userFilter as UserFilterRef | undefined;
            if (userFilter) {
                userFilter.term = userNumber;
            }

            void this.onSearch(userNumber);
            void this.onCheckCustomer(this.userData);
        },

        getList(): Promise<void> {
            this.isLoading = true;
            return this.userRepository
                .search(this.userCriteria)
                .then((users) => {
                    this.users = users;
                    // @ts-expect-error
                    this.total = users.total;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
        },

        isChecked(item: Entity<'user'>): boolean {
            return item.id === this.user?.id;
        },

        async onCheckCustomer(item: Entity<'user'>) {
            // If there's an existing user, save it as a draft.
            if (this.user) {
                this.userDraft = this.user;
            }

            this.user = await this.userRepository.get(item.id, Context.api, this.userCriterion);

            this.checkContextLanguage();

            // If the user belongs to a sales channel not in the allowed list and has no bound sales channel.
            if (!this.user?.boundSalesChannelId) {
                this.showSalesChannelSelectModal = true;

                return;
            }

            // If switching to a different user whose sales channel is different from the current one.
            if (
                this.userDraft &&
                this.user?.boundSalesChannelId &&
                this.userDraft.channelId !== this.user.boundSalesChannelId
            ) {
                this.showCustomerChangesModal = true;

                return;
            }

            void this.handleSelectCustomer();
        },

        createCart(channelId: EntityKey<'channel'>): Promise<void> {
            return Store.get('swOrder').createCart({ channelId });
        },

        setCustomer(user: Entity<'user'> | null): void {
            void Store.get('swOrder').selectExistingCustomer({ user });
        },

        async handleSelectCustomer(): Promise<void> {
            this.isSwitchingCustomer = true;

            try {
                if (!this.cart.token) {
                    // It is compulsory to create cart and get cart token first
                    await this.createCart(this.user?.channelId ?? ('' as EntityKey<'channel'>));
                }

                this.setCustomer(this.user);

                await this.updateCustomerContext();
            } catch (error) {
                let message = this.$t('sw-order.create.messageSwitchCustomerError');
                const errorCode = (error as ApiErrorResponse).response?.data?.errors?.[0]?.code;

                if (errorCode) {
                    const messageKey = `global.error-codes.${errorCode}`;
                    const translatedMessage = this.$t(messageKey);

                    if (translatedMessage !== messageKey) {
                        message = `${message}: ${translatedMessage}`;
                    }
                }

                this.createNotificationError({
                    message,
                });
            } finally {
                this.isSwitchingCustomer = false;
            }
        },

        onAddNewCustomer(userId: EntityKey<'user'>): void {
            if (!userId) {
                return;
            }

            // Refresh user list if new user is created successfully
            void this.getList();
            this.page = 1;
            this.term = '';
        },

        async updateCustomerContext(): Promise<void> {
            if (!this.user) return;

            await Store.get('swOrder')
                .updateCustomerContext({
                    userId: this.user.id,
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                })
                .then((response) => {
                    // Update cart after user context is updated
                    if (response.status === 200) {
                        void this.getCart();
                    }
                });
        },

        async getCart(): Promise<void> {
            if (!this.user) return;

            await Store.get('swOrder').getCart({
                channelId: this.user.channelId,
                contextToken: this.cart.token,
            });
        },

        async loadSalesChannel(): Promise<string[]> {
            const { data: ids } = await this.channelRepository.searchIds(this.channelCriteria);

            return ids;
        },

        onSalesChannelChange(channelId: EntityKey<'channel'>, channel: Entity<'channel'>): void {
            if (!this.user) {
                return;
            }

            this.user.channelId = channelId;
            this.user.channel = channel;

            this.checkContextLanguage();
        },

        onCloseSalesChannelSelectModal() {
            this.user = this.userDraft;

            this.showSalesChannelSelectModal = false;
        },

        async onSelectSalesChannel() {
            this.isLoading = true;

            try {
                await this.handleSelectCustomer();
            } finally {
                this.isLoading = false;
                this.showSalesChannelSelectModal = false;
            }
        },

        userUnavailable(user: Entity<'user'>): boolean {
            if (!this.channelIds.length) {
                return true;
            }

            return !!user?.boundSalesChannelId && !this.channelIds.includes(user.boundSalesChannelId);
        },

        async onChangeCustomer() {
            this.isLoading = true;
            try {
                await this.handleSelectCustomer();
            } finally {
                this.isLoading = false;
                this.showCustomerChangesModal = false;
            }
        },

        onCloseCustomerChangesModal() {
            this.user = this.userDraft;

            this.showCustomerChangesModal = false;
        },

        checkContextLanguage() {
            const exists = (this.user?.channel?.languages || []).some(
                (language) => language.id === Context.api.systemLanguageId,
            );

            if (!exists && this.user?.channel?.languageId) {
                Store.get('context').api.languageId = this.user.channel.languageId;
            }

            if (exists && !Store.get('context').isSystemDefaultLanguage) {
                Store.get('context').resetLanguageToDefault();
            }
        },
    },
});
