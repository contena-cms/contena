import { shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';

type IpSelectVm = {
    validKnownIps: Array<{ name: string; value: string }>;
    validUnselectedKnownIps: Array<{ name: string; value: string }>;
    getKnownIp: (ip: string) => { name: string; value: string } | null;
};

describe('ct-multi-tag-ip-select', () => {
    it.each([
        [
            'a676344c-c0dd-49e5-8fbb-5f570c27762c',
            false,
        ],
        [
            '::',
            true,
        ],
        [
            '10.0.0.1',
            true,
        ],
        [
            'aabb::',
            true,
        ],
        [
            '127.0.0.1abcd',
            false,
        ],
    ])('validates %s as %s', (value, shouldBeValid) => {
        expect(Contena.Utils.string.isValidIp(value)).toBe(shouldBeValid);
    });

    it('filters invalid and already selected known IP addresses', () => {
        const wrapper = shallowMount(component, {
            props: {
                value: ['127.0.0.1'],
                knownIps: [
                    { name: 'Current request', value: '127.0.0.1' },
                    { name: 'Private network', value: '10.0.0.1' },
                    { name: 'Invalid', value: 'invalid' },
                ],
            },
            global: { stubs: { 'ct-block': true, 'ct-multi-tag-select': true } },
        }) as unknown as VueWrapper<IpSelectVm>;

        expect(wrapper.vm.validKnownIps).toHaveLength(2);
        expect(wrapper.vm.validUnselectedKnownIps).toEqual([{ name: 'Private network', value: '10.0.0.1' }]);
        expect(wrapper.vm.getKnownIp('127.0.0.1')?.name).toBe('Current request');
    });
});
