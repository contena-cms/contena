import { mount } from '@vue/test-utils';
import 'src/module/ct-settings-message-stats/page/ct-settings-message-stats';

const mockStats = {
    enabled: true,
    stats: {
        totalMessagesProcessed: 100,
        processedSince: '2024-03-19T12:00:00.000Z',
        averageTimeInQueue: 1.5,
        messageTypeStats: [
            // unsorted
            { type: 'ThemeCompilation', count: 1 },
            { type: 'ProductIndexing', count: 50 },
            { type: 'MediaProcessing', count: 30 },
        ],
    },
};

const mockEmptyStats = {
    totalMessagesProcessed: 0,
    processedSince: null,
    averageTimeInQueue: 0,
    messageTypeStats: [],
};

async function createWrapper(options = {}) {
    return mount(
        await wrapTestComponent('ct-settings-message-stats', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    messageStatsService: {
                        getStats: options.getStatsMock || jest.fn().mockImplementation(() => new Promise(() => {})), // Never resolves to keep loading state
                    },
                },
                stubs: {
                    'ct-page': {
                        template: `<div class="ct-page">
                            <slot name="content"></slot>
                        </div>`,
                    },
                    'ct-card-view': {
                        template: '<div class="ct-card-view"><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot name="headerRight"></slot><slot></slot></div>',
                    },
                    'mt-data-table': true,
                    'ct-skeleton': true,
                    'ct-help-text': true,
                    'ct-data-grid': true,
                    'ct-card': true,
                    'ct-container': true,
                    'ct-button': true,
                    'mt-icon': true,
                },
                mocks: {
                    $t: (key) => `$t_${key}`,
                },
            },
        },
    );
}

describe('module/ct-settings-message-stats/page/ct-settings-message-stats', () => {
    it('should show loading state initially', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('ct-skeleton-stub').exists()).toBe(true);
        wrapper.unmount();
    });

    it('should load and display stats data', async () => {
        const wrapper = await createWrapper({
            getStatsMock: jest.fn().mockResolvedValue(mockStats),
        });

        await flushPromises();

        // Check if loading state is gone
        expect(wrapper.find('ct-skeleton-stub').exists()).toBe(false);

        // Check if data is displayed
        expect(wrapper.find('ct-data-grid-stub').exists()).toBe(true);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);

        // Check stat blocks
        const statItems = wrapper.findAll('.ct-settings-message-stats__stat-item');
        expect(statItems).toHaveLength(3);

        // Check total messages
        expect(statItems[0].find('.ct-settings-message-stats__stat-value').text()).toBe('100');

        // Check average time
        expect(statItems[1].find('.ct-settings-message-stats__stat-value').text()).toBe(
            '1.50ct-settings-message-stats.general.seconds',
        );

        // Check processing window
        expect(statItems[2].find('.ct-settings-message-stats__stat-value').text()).toBeTruthy();

        // Verify message type sorting
        const sortedStats = wrapper.vm.sortedMessageTypeStats;
        expect(sortedStats).toHaveLength(3);
        expect(sortedStats[0].type).toBe('ProductIndexing');
        expect(sortedStats[0].count).toBe(50);
        expect(sortedStats[1].type).toBe('MediaProcessing');
        expect(sortedStats[1].count).toBe(30);
        expect(sortedStats[2].type).toBe('ThemeCompilation');
        expect(sortedStats[2].count).toBe(1);

        wrapper.unmount();
    });

    it('should show empty state when no stats are available', async () => {
        const wrapper = await createWrapper({
            getStatsMock: jest.fn().mockResolvedValue(mockEmptyStats),
        });

        await flushPromises();

        // Check if loading state is gone
        expect(wrapper.find('ct-skeleton-stub').exists()).toBe(false);

        // Check if empty state is shown
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(wrapper.find('ct-data-grid-stub').exists()).toBe(false);

        // Check stat blocks
        const statItems = wrapper.findAll('.ct-settings-message-stats__stat-item');
        expect(statItems).toHaveLength(3);

        // Check total messages
        expect(statItems[0].find('.ct-settings-message-stats__stat-value').text()).toBe('—');

        // Check average time
        expect(statItems[1].find('.ct-settings-message-stats__stat-value').text()).toBe('—');

        // Check processing window
        expect(statItems[2].find('.ct-settings-message-stats__stat-value').text()).toBe('—');

        wrapper.unmount();
    });

    it('should refresh data when refresh button is clicked', async () => {
        const wrapper = await createWrapper({
            getStatsMock: jest.fn().mockResolvedValue(mockStats),
        });

        await flushPromises();

        // Find and click the refresh button
        const refreshButton = wrapper.find('.mt-button--secondary');
        expect(refreshButton.exists()).toBe(true);
        await refreshButton.trigger('click');

        await flushPromises();

        // Verify that getStats was called again
        expect(wrapper.vm.messageStatsService.getStats).toHaveBeenCalledTimes(2);

        wrapper.unmount();
    });

    it('should show disabled state when stats feature is disabled', async () => {
        const wrapper = await createWrapper({
            getStatsMock: jest.fn().mockResolvedValue({
                ...mockStats,
                enabled: false,
            }),
        });

        await flushPromises();

        // Check if loading state is gone
        expect(wrapper.find('ct-skeleton-stub').exists()).toBe(false);

        // Check if empty state is shown
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(wrapper.find('ct-data-grid-stub').exists()).toBe(false);

        // Check stat blocks
        const statItems = wrapper.findAll('.ct-settings-message-stats__stat-item');
        expect(statItems).toHaveLength(3);

        // Check total messages
        expect(statItems[0].find('.ct-settings-message-stats__stat-value').text()).toBe('—');

        // Check average time
        expect(statItems[1].find('.ct-settings-message-stats__stat-value').text()).toBe('—');

        // Check processing window
        expect(statItems[2].find('.ct-settings-message-stats__stat-value').text()).toBe('—');

        wrapper.unmount();
    });
});
