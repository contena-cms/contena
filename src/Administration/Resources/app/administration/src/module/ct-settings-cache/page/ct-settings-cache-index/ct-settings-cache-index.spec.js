import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

const cacheInfo = {
    data: {
        httpCache: true,
        environment: 'dev',
        cacheAdapter: 'fooBar',
        indexers: {
            'category.indexer': ['category.tree'],
        },
    },
};

async function createWrapper(indexMock = jest.fn(() => Promise.resolve()), delayMock = jest.fn(() => Promise.resolve())) {
    return mount(await wrapTestComponent('ct-settings-cache-index', { sync: true }), {
        global: {
            provide: {
                cacheInfo: cacheInfo,
                indexerSelection: [],
                cacheApiService: {
                    info: () => Promise.resolve(cacheInfo),
                    delayed: delayMock,
                    index: indexMock,
                },
            },
            stubs: {
                'ct-page': {
                    template: `
                    <div>
                        <slot name="smart-bar-header"></slot>
                        <slot name="content"></slot>
                    </div>`,
                },
                'ct-card-view': await wrapTestComponent('ct-card-view'),
                'ct-card-section': await wrapTestComponent('ct-card-section'),
                'ct-container': await wrapTestComponent('ct-container'),
                'ct-button-process': await wrapTestComponent('ct-button-process'),
                'ct-error-summary': await wrapTestComponent('ct-error-summary'),
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-field-error': await wrapTestComponent('ct-field-error'),
                'ct-skeleton': true,
                'ct-ai-copilot-badge': true,
                'ct-context-button': true,
                'ct-loader': true,
                'ct-iframe-renderer': true,
                'router-link': true,
                'ct-inheritance-switch': true,
                'ct-help-text': true,
                'ct-color-badge': true,
                'mt-popover-deprecated': {
                    template: '<div class="mt-popover-deprecated"><slot></slot></div>',
                },
            },
        },
    });
}

describe('module/ct-settings-cache/page/ct-settings-cache-index', () => {
    it('should change label and empty text on indexing method selection changed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const indexerSelect = wrapper.findAllComponents({ name: 'MtSelect' })[1];

        expect(indexerSelect.props('label')).toBe('ct-settings-cache.section.indexesSkipSelectLabel');
        expect(indexerSelect.props('placeholder')).toBe('ct-settings-cache.section.indexesSkipSelectPlaceholder');

        await selectMtSelectOptionByText(wrapper, 'ct-settings-cache.section.indexingModeOptionOnlyLabel');

        expect(indexerSelect.props('label')).toBe('ct-settings-cache.section.indexesOnlySelectLabel');
        expect(indexerSelect.props('placeholder')).toBe('ct-settings-cache.section.indexesOnlySelectPlaceholder');
    });

    it('should send clear data cache request', async () => {
        const mock = jest.fn(() => Promise.resolve());

        const wrapper = await createWrapper(
            jest.fn(() => Promise.resolve()),
            mock,
        );
        await flushPromises();

        wrapper.vm.clearDataCache();

        expect(mock).toHaveBeenCalledTimes(1);
    });

    it('should clear the selected indexers', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const indexerSelect = wrapper.findAllComponents({ name: 'MtSelect' })[1];
        await indexerSelect.vm.$emit('update:modelValue', ['category.indexer']);
        await indexerSelect.find('[data-testid="select-clear-button"]').trigger('click');

        expect(wrapper.vm.indexerSelection).toEqual([]);
    });

    it('should send different values for skip and only on reindex', async () => {
        const indexMock = jest.fn(() => Promise.resolve());

        const wrapper = await createWrapper(indexMock);
        await flushPromises();

        expect(wrapper.vm.indexerSelection).toHaveLength(0);

        wrapper.vm.changeSelection(true, 'category.tree');

        expect(wrapper.vm.indexerSelection).toHaveLength(1);

        const button = wrapper.find('button[name="updateIndexesButton"]');

        await button.trigger('click');
        await flushPromises();

        expect(indexMock).toHaveBeenCalledTimes(1);
        expect(indexMock).toHaveBeenCalledWith(['category.tree'], []);

        await selectMtSelectOptionByText(wrapper, 'ct-settings-cache.section.indexingModeOptionOnlyLabel');

        wrapper.vm.changeSelection(true, 'category.indexer');

        await button.trigger('click');
        await flushPromises();

        expect(indexMock).toHaveBeenCalledTimes(2);
        expect(indexMock).toHaveBeenCalledWith([], ['category.indexer']);
    });
});
