import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

async function createWrapper() {
    const media = [
        {
            id: 'media-id',
            fileName: 'document',
            fileExtension: 'pdf',
            private: false,
        },
    ];
    media.total = media.length;

    const wrapper = mount(
        await wrapTestComponent('ct-settings-tag-detail-assignments', {
            sync: true,
        }),
        {
            props: {
                tag: {
                    id: 'tag-id',
                    isNew: () => true,
                },
                toBeAdded: { media: {} },
                toBeDeleted: { media: {} },
                initialCounts: { media: 1 },
            },
            global: {
                provide: {
                    [routeLocationKey]: {
                        name: 'tag-detail-assignments',
                        query: {},
                        params: {},
                    },
                    [routerKey]: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                    repositoryFactory: {
                        create: () => ({
                            search: jest.fn(() => Promise.resolve(media)),
                            searchIds: jest.fn(() => Promise.resolve({ data: [], total: 0 })),
                        }),
                    },
                    searchRankingService: {
                        isValidTerm: (term) => Boolean(term?.trim()),
                    },
                },
                stubs: {
                    'ct-card-section': true,
                    'ct-container': true,
                    'ct-card-filter': true,
                    'ct-entity-listing': true,
                    'ct-media-preview-v2': true,
                    'ct-highlight-text': true,
                },
            },
        },
    );

    await flushPromises();

    return wrapper;
}

describe('module/ct-settings-tag/component/ct-settings-tag-detail-assignments', () => {
    it('uses the retained media association by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.selectedEntity).toBe('media');
        expect(wrapper.vm.selectedAssignment).toBe('media');
        expect(wrapper.vm.assignmentAssociations).toEqual([
            {
                name: 'ct-settings-tag.detail.assignments.media',
                entity: 'media',
                assignment: 'media',
            },
        ]);
        expect(wrapper.vm.entities).toHaveLength(1);
    });

    it('emits media assignment changes and updates the count', async () => {
        const wrapper = await createWrapper();
        const media = { id: 'media-id' };

        wrapper.vm.onSelectionChange([], media, false);
        expect(wrapper.emitted('remove-assignment')[0]).toEqual([
            'media',
            'media-id',
            media,
        ]);
        expect(wrapper.vm.getCount('media')).toBe(0);

        wrapper.vm.onSelectionChange([], media, true);
        expect(wrapper.emitted('add-assignment')[0]).toEqual([
            'media',
            'media-id',
            media,
        ]);
        expect(wrapper.vm.getCount('media')).toBe(1);
    });
});
