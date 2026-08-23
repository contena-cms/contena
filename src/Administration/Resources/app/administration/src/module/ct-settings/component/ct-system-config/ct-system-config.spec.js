import { shallowMount } from '@vue/test-utils';

let wrapper;

function createConfig(cacheRelevant = false) {
    return [
        {
            title: { 'en-GB': 'Settings' },
            elements: [
                {
                    name: 'example.config.value',
                    type: 'text',
                    config: {
                        label: { 'en-GB': 'Value' },
                        cacheRelevant,
                    },
                },
            ],
        },
    ];
}

async function createWrapper({
    values = {},
    channelValues = {},
    config = createConfig(),
    channelId = null,
    channelSwitchable = false,
} = {}) {
    const systemConfigApiService = {
        getConfig: jest.fn(() => Promise.resolve(config)),
        getValues: jest.fn((domain, selectedChannelId) => Promise.resolve(selectedChannelId ? channelValues : values)),
        batchSave: jest.fn(() => Promise.resolve()),
    };

    wrapper = shallowMount(await wrapTestComponent('ct-system-config'), {
        props: {
            domain: 'example.config',
            channelId,
            channelSwitchable,
        },
        global: {
            mocks: {
                $t: (key) => key,
            },
            provide: {
                systemConfigApiService,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/ct-settings/component/ct-system-config/ct-system-config', () => {
    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('loads global configuration', async () => {
        wrapper = await createWrapper({
            values: { 'example.config.value': 'global' },
        });

        expect(wrapper.vm.systemConfigApiService.getValues).toHaveBeenCalledWith('example.config', null);
        expect(wrapper.vm.actualConfigData).toEqual({ null: { 'example.config.value': 'global' } });
    });

    it('saves only changed values', async () => {
        wrapper = await createWrapper({
            values: { 'example.config.value': 'before' },
        });

        wrapper.vm.actualConfigData.null['example.config.value'] = 'after';
        await wrapper.vm.saveAll();

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith(
            { null: { 'example.config.value': 'after' } },
            {},
        );
    });

    it('invalidates dependent caches for cache-relevant changes', async () => {
        wrapper = await createWrapper({
            values: { 'example.config.value': 'before' },
            config: createConfig(true),
        });

        wrapper.vm.actualConfigData.null['example.config.value'] = 'after';
        await wrapper.vm.saveAll();

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith(
            { null: { 'example.config.value': 'after' } },
            { silent: false },
        );
    });

    it('does not save unchanged configuration', async () => {
        wrapper = await createWrapper({
            values: { 'example.config.value': 'unchanged' },
        });

        await wrapper.vm.saveAll();

        expect(wrapper.vm.systemConfigApiService.batchSave).not.toHaveBeenCalled();
    });

    it('loads and saves a channel override independently from global values', async () => {
        wrapper = await createWrapper({
            values: { 'example.config.value': 'global' },
            channelValues: { 'example.config.value': 'channel' },
            channelId: 'channel-1',
            channelSwitchable: true,
        });

        expect(wrapper.vm.systemConfigApiService.getValues).toHaveBeenCalledWith('example.config', 'channel-1');
        wrapper.vm.actualConfigData['channel-1']['example.config.value'] = 'changed';
        await wrapper.vm.saveAll();

        expect(wrapper.vm.systemConfigApiService.batchSave).toHaveBeenCalledWith(
            { 'channel-1': { 'example.config.value': 'changed' } },
            {},
        );
    });
});
