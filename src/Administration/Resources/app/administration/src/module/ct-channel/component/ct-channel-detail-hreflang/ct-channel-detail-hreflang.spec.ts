import { mount } from '@vue/test-utils';
import component from './index';

const mtSwitchStub = {
    name: 'mt-switch',
    template: '<div class="mt-switch"></div>',
    props: [
        'disabled',
        'modelValue',
    ],
};
const mtEntitySelectStub = {
    name: 'mt-entity-select',
    template: '<div class="mt-entity-select"></div>',
    props: [
        'disabled',
        'repository',
    ],
};

function createWrapper(customProps: Record<string, unknown> = {}) {
    return mount(component, {
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<div class="mt-card"><slot /></div>' },
                'mt-switch': mtSwitchStub,
                'mt-entity-select': mtEntitySelectStub,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve([]) }),
                },
            },
        },
        props: {
            channel: {
                id: 'channel-id',
                hreflangActive: true,
            },
            ...customProps,
        },
    });
}

describe('src/module/ct-channel/component/ct-channel-detail-hreflang', () => {
    it('should enable the switch and the entity select', () => {
        const wrapper = createWrapper();

        expect(wrapper.getComponent(mtSwitchStub).props('disabled')).toBeUndefined();
        expect(wrapper.getComponent(mtEntitySelectStub).props('disabled')).toBeUndefined();
    });

    it('should disable the switch and the entity select', () => {
        const wrapper = createWrapper({ disabled: true });

        expect(wrapper.getComponent(mtSwitchStub).props('disabled')).toBe(true);
        expect(wrapper.getComponent(mtEntitySelectStub).props('disabled')).toBe(true);
    });
});
