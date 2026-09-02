import { mount } from '@vue/test-utils';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import component from './index';

const channel = {
    id: 'channel-1',
    translated: {
        name: 'Web',
    },
} as Entity<'channel'>;

function createWrapper(channelName = 'Web') {
    const visibility = {
        id: 'visibility-1',
        blogId: 'blog-1',
        blogVersionId: 'version-1',
        channelId: channel.id,
        visibility: 30,
        channel: {
            id: channel.id,
            translated: { name: channelName },
        } as Entity<'channel'>,
    } as Entity<'blog_visibility'>;
    const store = Contena.Store.get('ctBlogDetail');
    store.$reset();
    store.blog = {
        id: 'blog-1',
        visibilities: [visibility],
        getEntityName: () => 'blog',
    } as unknown as Entity<'blog'> & { isNew: () => boolean };

    const wrapper = mount(component, {
        global: {
            stubs: {
                'ct-radio-field': {
                    props: ['options'],
                    emits: ['update:value'],
                    template:
                        '<input type="radio" :value="options[0].value" @change="$emit(\'update:value\', options[0].value)" />',
                },
                'ct-grid': {
                    props: ['items'],
                    template:
                        '<div><template v-for="item in items" :key="item.id"><slot name="columns" :item="item" /></template><slot name="pagination" /></div>',
                },
                'ct-pagination': true,
                'ct-grid-column': {
                    template: '<div><slot /></div>',
                },
                'ct-field-error': true,
            },
        },
    });

    return { wrapper, visibility };
}

describe('module/ct-blog/component/ct-blog-visibility-detail', () => {
    it('changes the visibility value', async () => {
        const { wrapper, visibility } = createWrapper();
        await flushPromises();

        expect(wrapper.find('.ct-blog-visibility-detail__name').text()).toBe('Web');
        const radio = wrapper.find('.ct-blog-visibility-detail__link-only input');

        await radio.trigger('change');

        expect(visibility.visibility).toBe(10);
    });

    it('shows a tooltip when the Channel name is truncated', async () => {
        const name = 'WayTooLongChannelNameThatWillBeTruncated';
        const { wrapper } = createWrapper(name);
        await flushPromises();

        const nameElement = wrapper.find('.ct-blog-visibility-detail__name');

        expect(nameElement.text().endsWith('...')).toBe(true);
        expect(nameElement.attributes()['tooltip-mock-message']).toBe(name);
    });
});
