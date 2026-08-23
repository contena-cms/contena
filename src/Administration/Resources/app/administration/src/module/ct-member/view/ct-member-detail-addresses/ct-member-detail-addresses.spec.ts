import { shallowMount, type VueWrapper } from '@vue/test-utils';
import Entity from 'src/core/data/entity.data';
import component from './ct-member-detail-addresses.vue';

describe('module/ct-member/view/ct-member-detail-addresses', () => {
    let wrapper: VueWrapper | undefined;
    let address: Entity<'member_address'>;
    let member: Entity<'member'>;
    let getAddress: jest.Mock;
    let saveAddress: jest.Mock;
    let searchAddresses: jest.Mock;

    beforeEach(async () => {
        address = new Entity('address-on-later-page', 'member_address', {
            id: 'address-on-later-page',
            memberId: 'member-1',
            countryId: 'country-1',
            firstName: 'Max',
            lastName: 'Mustermann',
            city: 'Schoeppingen',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
        } as EntitySchema.Entities['member_address']);
        getAddress = jest.fn().mockResolvedValue(address);
        saveAddress = jest.fn().mockResolvedValue(undefined);
        searchAddresses = jest.fn().mockResolvedValue([address]);
        member = new Entity('member-1', 'member', {
            addresses: [],
        } as unknown as EntitySchema.Entities['member']);

        wrapper = shallowMount(component, {
            props: {
                member,
                memberEditMode: true,
            },
            global: {
                stubs: {
                    'mt-card': { template: '<div><slot name="toolbar"/><slot/></div>' },
                    'mt-data-table': {
                        props: ['dataSource'],
                        template: '<div><slot name="column-name" :data="dataSource[0]"/></div>',
                    },
                    'mt-link': {
                        template: '<button class="edit-address" @click="$emit(\'click\')"><slot/></button>',
                    },
                    'mt-modal-root': { template: '<div><slot/></div>' },
                    'mt-modal': { template: '<div><slot/><slot name="footer"/></div>' },
                    'mt-button': { template: '<button @click="$emit(\'click\')"><slot/></button>' },
                    'ct-member-address-form': { template: '<div class="member-address-form" />' },
                },
                provide: {
                    acl: { can: jest.fn(() => true) },
                    repositoryFactory: {
                        create: jest.fn(() => ({
                            create: jest.fn(),
                            delete: jest.fn(),
                            get: getAddress,
                            save: saveAddress,
                            search: searchAddresses,
                        })),
                    },
                },
            },
        });

        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('loads an address by id when opening the edit modal', async () => {
        expect((member.addresses ?? []).find((item) => item.id === 'address-on-later-page')).toBeUndefined();

        await wrapper!.get('.edit-address').trigger('click');
        await flushPromises();

        expect(getAddress).toHaveBeenCalledWith('address-on-later-page', Contena.Context.api, expect.anything());
        expect(wrapper!.find('.member-address-form').exists()).toBe(true);
    });

    it('uses the shared table context column for address actions', () => {
        const vm = wrapper!.vm as unknown as {
            columns: Array<{ property: string }>;
            additionalContextButtons: Array<{ key: string; label: string }>;
        };

        expect(vm.columns).not.toContainEqual(expect.objectContaining({ property: 'actions' }));
        expect(vm.additionalContextButtons).toEqual([
            { key: 'edit', label: 'global.default.edit' },
            { key: 'duplicate', label: 'global.default.duplicate' },
        ]);
    });

    it('saves an address loaded by id', async () => {
        await wrapper!.get('.edit-address').trigger('click');
        await flushPromises();
        address.city = 'Berlin';

        const saveButton = wrapper!.findAll('button').find((button) => button.text() === 'global.default.save');
        await saveButton!.trigger('click');
        await flushPromises();

        expect(saveAddress).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'address-on-later-page',
                city: 'Berlin',
            }),
            Contena.Context.api,
        );
        expect(wrapper!.find('.member-address-form').exists()).toBe(false);
    });
});
