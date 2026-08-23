import { shallowMount, type VueWrapper } from '@vue/test-utils';
import Entity from 'src/core/data/entity.data';
import component from './index';

interface AddressFormVm {
    provinceId: string | null;
    cityRegionId: string | null;
    districtId: string | null;
    loadRegionPath: () => Promise<void>;
    onCountryChange: () => void;
    onProvinceChange: () => void;
    onCityRegionChange: () => void;
    onDistrictChange: () => void;
}

describe('module/ct-member/component/ct-member-address-form', () => {
    let wrapper: VueWrapper | undefined;
    let address: Entity<'member_address'>;
    let getRegion: jest.Mock;

    beforeEach(async () => {
        address = new Entity('address-1', 'member_address', {
            countryId: 'country-1',
            regionId: 'district-1',
        } as EntitySchema.Entities['member_address']);
        getRegion = jest.fn().mockResolvedValue({
            id: 'district-1',
            parent: {
                id: 'city-1',
                parent: { id: 'province-1' },
            },
        });

        wrapper = shallowMount(component, {
            props: {
                member: new Entity('member-1', 'member', {} as EntitySchema.Entities['member']),
                address,
            },
            global: {
                stubs: {
                    'mt-entity-select': true,
                    'mt-text-field': true,
                },
                provide: {
                    repositoryFactory: {
                        create: jest.fn(() => ({ get: getRegion })),
                    },
                },
            },
        });

        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('loads the Province, City and District path from the stored Region', () => {
        const vm = wrapper!.vm as unknown as AddressFormVm;

        expect(getRegion).toHaveBeenCalledWith('district-1', Contena.Context.api, expect.anything());
        expect(vm.provinceId).toBe('province-1');
        expect(vm.cityRegionId).toBe('city-1');
        expect(vm.districtId).toBe('district-1');
    });

    it('clears the Region selection when the Country changes', () => {
        const vm = wrapper!.vm as unknown as AddressFormVm;

        vm.onCountryChange();

        expect(vm.provinceId).toBeNull();
        expect(vm.cityRegionId).toBeNull();
        expect(vm.districtId).toBeNull();
        expect(address.regionId).toBeUndefined();
    });

    it('stores the deepest selected Region on the address', () => {
        const vm = wrapper!.vm as unknown as AddressFormVm;

        vm.provinceId = 'province-2';
        vm.onProvinceChange();
        expect(address.regionId).toBe('province-2');

        vm.cityRegionId = 'city-2';
        vm.onCityRegionChange();
        expect(address.regionId).toBe('city-2');

        vm.districtId = 'district-2';
        vm.onDistrictChange();
        expect(address.regionId).toBe('district-2');
    });
});
