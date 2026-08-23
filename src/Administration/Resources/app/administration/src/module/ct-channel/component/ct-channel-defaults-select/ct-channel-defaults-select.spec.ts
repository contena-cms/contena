import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';

type DefaultsSelectVm = {
    updateDefault: (id: string) => Promise<void>;
    defaultId: string | null;
    propertyCollection: {
        entity: string;
        has: (id: string) => boolean;
    };
};

const mtEntitySelectStub = {
    name: 'mt-entity-select',
    template: '<div class="mt-entity-select"></div>',
    props: [
        'modelValue',
        'disabled',
        'entity',
        'enableMultiSelection',
        'repository',
    ],
};

function createChannel() {
    const channel = {
        countries: new Contena.Data.EntityCollection('/country', 'country', Contena.Context.api),
        getEntityName: () => 'channel',
    };

    return channel;
}

function createWrapper(customProps: Record<string, unknown> = {}): VueWrapper<DefaultsSelectVm> {
    return shallowMount(component, {
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-entity-select': mtEntitySelectStub,
                'mt-icon': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: (id: string) => Promise.resolve({ id, name: 'Germany' }),
                        search: () => Promise.resolve([]),
                    }),
                },
            },
        },
        props: {
            channel: createChannel(),
            propertyName: 'countries',
            propertyLabel: '',
            defaultPropertyName: 'countryId',
            defaultPropertyLabel: '',
            ...customProps,
        },
    }) as unknown as VueWrapper<DefaultsSelectVm>;
}

describe('src/module/ct-channel/component/ct-channel-defaults-select', () => {
    it('should have selects enabled', () => {
        const wrapper = createWrapper();
        const [
            multiSelect,
            singleSelect,
        ] = wrapper.findAllComponents(mtEntitySelectStub);

        expect(multiSelect.props('disabled')).toBeUndefined();
        expect(singleSelect.props('disabled')).toBeUndefined();
    });

    it('should have selects disabled', () => {
        const wrapper = createWrapper({ disabled: true });
        const [
            multiSelect,
            singleSelect,
        ] = wrapper.findAllComponents(mtEntitySelectStub);

        expect(multiSelect.props('disabled')).toBe(true);
        expect(singleSelect.props('disabled')).toBe(true);
    });

    it('should keep the entity collection metadata when adding a default entity', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.updateDefault('country-id');
        await flushPromises();

        const singleSelect = wrapper.findAllComponents(mtEntitySelectStub)[1];
        expect(wrapper.vm.defaultId).toBe('country-id');
        expect(wrapper.vm.propertyCollection.entity).toBe('country');
        expect(wrapper.vm.propertyCollection.has('country-id')).toBe(true);
        expect(singleSelect.props('entity')).toBe('country');
    });
});
