import template from './sw-order-user-address-select.html.twig';
import './sw-order-user-address-select.scss';
import type CriteriaType from '../../../../core/data/criteria.data';
import type Repository from '../../../../core/data/repository.data';

/**
 * @sw-package checkout
 */

const { Component } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    props: {
        user: {
            type: Object as PropType<Entity<'user'>>,
            required: true,
        },

        value: {
            type: String as PropType<string>,
            required: true,
        },

        sameAddressLabel: {
            type: String as PropType<string>,
            required: false,
            default: '',
        },

        sameAddressValue: {
            type: String as PropType<string>,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        userAddresses: Entity<'user_address'>[];
        isLoading: boolean;
        addressSearchTerm: string;
    } {
        return {
            userAddresses: [],
            isLoading: false,
            addressSearchTerm: '',
        };
    },

    computed: {
        addressId: {
            get(): string {
                return this.value;
            },
            set(newValue: string | null): void {
                if (newValue === null) {
                    return;
                }

                this.$emit('update:value', newValue);
                return;

                this.$emit('change', newValue);
            },
        },

        isSameAddress(): boolean {
            return this.value === this.sameAddressValue;
        },

        addressRepository(): Repository<'user_address'> {
            return this.repositoryFactory.create(
                this.user?.addresses?.entity ?? 'user_address',
                this.user?.addresses?.source,
            );
        },

        addressCriteria(): CriteriaType {
            const criteria = new Criteria(1, null);
            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            if (this.addressSearchTerm) {
                criteria.setTerm(this.addressSearchTerm);
            }

            return criteria;
        },
    },

    watch: {
        'user.id': {
            handler(): void {
                void this.getCustomerAddresses();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.getCustomerAddresses();
        },

        getSelectionLabel(item: Entity<'user_address'>): string {
            if (this.isSameAddress && this.sameAddressLabel) {
                return this.sameAddressLabel;
            }

            return this.getCustomerAddress(item);
        },

        getCustomerAddress(address: Entity<'user_address'>): string {
            if (!address) return '';

            const result: Array<string> = [];

            const properties = [
                'street',
                'zipcode',
                'city',
                'countryState',
                'country',
            ] as const;

            properties.forEach((property) => {
                const adressProperty = address[property];

                if (!adressProperty) {
                    return;
                }

                if (property === 'countryState' || property === 'country') {
                    const name = address[property]?.translated?.name;

                    if (name) {
                        result.push(name);
                    }

                    return;
                }

                result.push(<string>address[property]);
            });

            return result.join(', ');
        },

        getCustomerAddresses(): Promise<void> {
            this.isLoading = true;

            // Get the latest addresses from user's db
            return this.addressRepository
                .search(this.addressCriteria)
                .then((addresses: EntityCollection<'user_address'>): void => {
                    this.userAddresses = this.getDisplayedCustomerAddresses(addresses);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        searchAddress(searchTerm: string): Promise<void> {
            this.isLoading = true;

            this.addressSearchTerm = searchTerm;

            return this.addressRepository
                .search(this.addressCriteria)
                .then((addresses: EntityCollection<'user_address'>) => {
                    this.userAddresses = this.getDisplayedCustomerAddresses(addresses);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        searchAddressResults() {
            return this.userAddresses;
        },

        getDisplayedCustomerAddresses(addresses: EntityCollection<'user_address'>): Entity<'user_address'>[] {
            const userAddresses = [...addresses];

            if (!this.addressId || userAddresses.some((address) => address.id === this.addressId)) {
                return userAddresses;
            }

            const selectedAddress = this.userAddresses.find((address) => address.id === this.addressId);

            if (selectedAddress) {
                userAddresses.unshift(selectedAddress);
            }

            return userAddresses;
        },
    },
});
