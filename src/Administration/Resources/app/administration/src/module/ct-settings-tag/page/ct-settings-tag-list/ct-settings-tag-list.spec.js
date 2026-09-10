import { mount } from '@vue/test-utils';
import { defineComponent, onMounted, ref } from 'vue';
import { routeLocationKey, routerKey } from 'vue-router';

const CtMeteorEntityDataTableStub = defineComponent({
    name: 'CtMeteorEntityDataTable',
    props: {
        repository: { type: Object, required: true },
        columns: { type: Array, default: () => [] },
        caption: { type: String, default: '' },
        allowEdit: Boolean,
            allowDelete: Boolean,
            showSelections: Boolean,
            showOutlines: Boolean,
            showStripes: Boolean,
            enableOutlineFraming: Boolean,
            enableRowNumbering: Boolean,
        disableSearch: Boolean,
        additionalContextButtons: { type: Array, default: () => [] },
    },
    emits: [
        'load-success',
        'loading-change',
        'total-change',
        'selected-ids-change',
        'context-select',
        'open-detail',
        'selection-change',
        'multiple-selection-change',
        'item-delete',
        'reload',
    ],
    setup(props, { emit }) {
        const dataSource = ref([]);

        onMounted(async () => {
            emit('loading-change', true);
            const result = await props.repository.search({});
            dataSource.value = result;
            emit('load-success', { records: result, total: result.total ?? result.length });
            emit('total-change', result.total ?? result.length);
            emit('loading-change', false);
        });

        return { dataSource };
    },
    template: `
        <mt-data-table
            :data-source="dataSource"
            :columns="columns"
            :pagination-total-items="dataSource.total"
            :disable-search="disableSearch"
            :allow-row-selection="showSelections"
            :disable-edit="!allowEdit"
            :disable-delete="!allowDelete"
            :additional-context-buttons="additionalContextButtons"
            @context-select="$emit('context-select', $event)"
            @selection-change="$emit('selection-change', $event)"
            @multiple-selection-change="$emit('multiple-selection-change', $event)"
            @item-delete="$emit('item-delete', $event)"
            @reload="$emit('reload')"
            @change-show-outlines="$emit('change-show-outlines', $event)"
            @change-show-stripes="$emit('change-show-stripes', $event)"
            @change-outline-framing="$emit('change-outline-framing', $event)"
            @change-enable-row-numbering="$emit('change-enable-row-numbering', $event)"
        >
            <template v-if="$slots.toolbar" #toolbar><slot name="toolbar" /></template>
            <template #column-name="scope"><slot name="column-name" v-bind="scope" /></template>
            <template #empty-state><slot name="empty-state" /></template>
        </mt-data-table>
    `,
});

const connections = {
    media: 112,
    users: 7,
};
const deleteEndpoint = jest.fn(() => Promise.resolve());
const cloneEndpoint = jest.fn(() => Promise.resolve());
const wrappers = [];

async function createWrapper(privileges = []) {
    const responseMock = [
        {
            id: '1',
            name: 'ExampleTag',
        },
        {
            id: '2',
            name: 'AnotherExampleTag',
        },
    ];

    responseMock.aggregations = {};
    responseMock.total = 2;

    Object.keys(connections).forEach((connection) => {
        responseMock.aggregations[connection] = {
            buckets: [
                {
                    key: '1',
                    [connection]: {
                        count: connections[connection],
                    },
                },
            ],
        };
    });

    const wrapper = mount(
        await wrapTestComponent('ct-settings-tag-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        meta: {
                            $module: {
                                icon: 'solid-tags',
                            },
                        },
                    },
                },
                provide: {
                    [routeLocationKey]: {
                        name: 'tag-list',
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        params: {},
                    },
                    [routerKey]: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                    repositoryFactory: {
                        create: () => ({
                            records: responseMock,
                            search: () => {
                                return Promise.resolve(responseMock);
                            },

                            delete: deleteEndpoint,

                            clone: cloneEndpoint,
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                    tagApiService: {
                        filterIds: jest.fn(() => Promise.resolve({ total: 1, ids: ['1'] })),
                    },
                },
                stubs: {
                    'ct-meteor-entity-data-table': CtMeteorEntityDataTableStub,
                    'ct-page': {
                        template: `
                    <div class="ct-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'ct-card-view': {
                        template: `
                    <div class="ct-card-view">
                        <slot></slot>
                    </div>
                `,
                    },
                    'mt-card': {
                        template: `
                    <div class="mt-card">
                        <slot name="grid"></slot>
                    </div>
                `,
                    },
                    'mt-data-table': {
                        name: 'MtDataTable',
                        props: {
                            dataSource: {
                                type: Array,
                                default: () => [],
                            },
                            columns: {
                                type: Array,
                                default: () => [],
                            },
                            disableSearch: Boolean,
                            disableEdit: Boolean,
                            disableDelete: Boolean,
                            disableSettingsTable: Boolean,
                            allowRowSelection: Boolean,
                            showOutlines: Boolean,
                            showStripes: Boolean,
                            enableOutlineFraming: Boolean,
                            enableRowNumbering: Boolean,
                            additionalContextButtons: {
                                type: Array,
                                default: () => [],
                            },
                            selectedRows: {
                                type: Array,
                                default: () => [],
                            },
                            paginationTotalItems: Number,
                            currentPage: Number,
                            paginationLimit: Number,
                            sortBy: String,
                            sortDirection: String,
                        },
                        template: `
                    <div class="mt-data-table-stub">
                        <div v-if="$slots.toolbar" class="mt-data-table-toolbar-stub">
                            <slot name="toolbar"></slot>
                        </div>
                        <template v-for="item in dataSource" :key="item.id">
                            <slot name="column-name" v-bind="{ data: item }"></slot>
                        </template>
                        <slot v-if="dataSource.length === 0" name="empty-state"></slot>
                    </div>
                `,
                    },
                    'ct-context-menu-item': true,
                    'ct-search-bar': true,
                    'ct-loader': true,
                    'mt-modal-root': {
                        props: {
                            isOpen: Boolean,
                        },
                        template: `
                    <div v-if="isOpen" class="mt-modal-root-stub">
                        <slot></slot>
                    </div>
                `,
                    },
                    'mt-modal': {
                        props: {
                            title: String,
                            width: String,
                        },
                        template: `
                    <div class="mt-modal-stub">
                        <slot></slot>
                        <slot name="footer"></slot>
                        </div>
                    `,
                    },
                    'mt-modal-close': true,
                    'mt-modal-action': true,
                    'ct-card-filter': true,
                    'ct-context-menu-divider': true,

                    'mt-select': true,
                    'ct-context-button': true,

                    'ct-label': true,
                    'ct-text-field': true,
                    'mt-badge': true,
                    'ct-settings-tag-detail-modal': true,
                },
            },
        },
    );

    wrappers.push(wrapper);

    await flushPromises();

    return wrapper;
}

describe('module/ct-settings-tag/page/ct-settings-tag-list', () => {
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

    it('uses the Meteor data table for the tag listing', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });

        expect(wrapper.find('ct-entity-listing-stub').exists()).toBe(false);
        expect(wrapper.find('.ct-card-view').exists()).toBe(true);
        expect(wrapper.find('.mt-data-table-toolbar-stub').exists()).toBe(false);
        expect(table.props('dataSource')).toHaveLength(2);
        expect(table.props('paginationTotalItems')).toBe(2);
        expect(table.props('disableSearch')).toBe(true);
        expect(table.props('allowRowSelection')).toBe(false);
        expect(table.props('columns')).toEqual(
            expect.arrayContaining([expect.objectContaining({ property: 'name', renderer: 'text', position: 100 })]),
        );
        expect(table.props('columns')).not.toEqual(
            expect.arrayContaining([expect.objectContaining({ property: 'actions' })]),
        );
        expect(table.props('disableSettingsTable')).toBe(false);
    });

    it('controls Meteor row selection for bulk merge actions', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.onSelectedIdsChange(['1', '2']);

        expect(wrapper.vm.selectedTagIds).toEqual([
            '1',
            '2',
        ]);
        expect(Object.keys(wrapper.vm.tagSelection)).toEqual([
            '1',
            '2',
        ]);

        wrapper.vm.onSelectedIdsChange(['2']);

        expect(wrapper.vm.selectedTagIds).toEqual(['2']);
        expect(Object.keys(wrapper.vm.tagSelection)).toEqual(['2']);
    });

    it('handles the built-in Meteor row actions', async () => {
        const wrapper = await createWrapper([
            'tag.creator',
            'tag.editor',
            'tag.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        table.vm.$emit('context-select', { key: 'edit', data: { id: '1', name: 'ExampleTag' } });
        expect(wrapper.vm.showDetailModal).toBe('1');

        wrapper.vm.onCloseDetailModal();
        table.vm.$emit('context-select', { key: 'duplicate', data: { id: '1', name: 'ExampleTag' } });
        expect(wrapper.vm.showDuplicateModal).toBe('1');

        wrapper.vm.onCloseDuplicateModal();
        expect(table.props('disableDelete')).toBe(false);
    });

    it('should use the single contextual search without a duplicate card search', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('mt-search')).toHaveLength(1);
        expect(wrapper.find('ct-card-filter-stub').exists()).toBe(false);
    });

    it('uses Meteor modal components for tag actions', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.onDuplicate({ id: '1', name: 'ExampleTag' });
        wrapper.vm.showBulkMergeModal = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.mt-modal-root-stub')).toHaveLength(2);
        expect(wrapper.findAll('.mt-modal-stub')).toHaveLength(2);
        expect(wrapper.findAll('.ct-settings-tag-list__modal-footer')).toHaveLength(2);
        expect(wrapper.find('ct-modal-stub').exists()).toBe(false);
    });

    it('should be able to create a new tag', async () => {
        const wrapper = await createWrapper([
            'tag.creator',
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.ct-settings-tag-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('additionalContextButtons')).toEqual([
            {
                key: 'duplicate',
                label: 'global.default.duplicate',
            },
        ]);
    });

    it('should not be able to create a new tag', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.ct-settings-tag-list__button-create');

        expect(addButton.attributes('disabled')).toBeDefined();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('additionalContextButtons')).toEqual([]);
    });

    it('should be able to edit a tag', async () => {
        const wrapper = await createWrapper([
            'tag.editor',
        ]);
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('disableEdit')).toBe(false);
        expect(table.props('additionalContextButtons')).toEqual([
            {
                key: 'edit',
                label: 'global.default.edit',
            },
        ]);
    });

    it('should not be able to edit a tag', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('disableEdit')).toBe(true);
        expect(table.props('additionalContextButtons')).toEqual([]);
    });

    it('updates the Meteor table display settings', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });

        expect(table.props('disableSettingsTable')).toBe(false);
    });

    it('only renders the table toolbar when bulk merge is available', async () => {
        const wrapper = await createWrapper([
            'tag.creator',
            'tag.deleter',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.mt-data-table-toolbar-stub').exists()).toBe(false);

        wrapper.vm.onSelectedIdsChange([
            '1',
            '2',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.mt-data-table-toolbar-stub').exists()).toBe(true);
    });

    it('should be able to delete a tag', async () => {
        const wrapper = await createWrapper([
            'tag.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('disableDelete')).toBe(false);
    });

    it('should not be able to delete a tag', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('disableDelete')).toBe(true);
    });

    it('should return summary of total connections', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const expected = {};
        Object.entries(connections).forEach(
            ([
                propertyName,
                count,
            ]) => {
                if (!count) {
                    return;
                }

                expected[propertyName] = count;
            },
        );
        const counts = wrapper.vm.getCounts('1');

        expect(counts).toEqual(expected);
    });

    it('should return total of single assignment', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.getPropertyCounting('media', '1')).toBe(112);
        expect(wrapper.vm.getPropertyCounting('invalid', '1')).toBe(0);
        expect(wrapper.vm.getPropertyCounting('media', 'invalid')).toBe(0);
    });

    it('should use tag api service for duplicate filter', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.sortBy = 'media';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.total).toBe(2);

        wrapper.vm.duplicateFilter = true;
        await wrapper.vm.$nextTick();

        await wrapper.vm.transformTagCriteria(wrapper.vm.tagCriteria);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tagApiService.filterIds).toHaveBeenCalledTimes(1);
    });

    it('should return sorted many to many assignment filter options', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const options = wrapper.vm.assignmentFilterOptions;

        const expected = [
            'media',
            'rules',
            'users',
        ].map((value) => {
            return {
                value,
                label: `ct-settings-tag.list.assignments.filter.${value}`,
            };
        });

        expect(options).toEqual(expected);
    });

    it('should return count of filters', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(0).toEqual(wrapper.vm.filterCount);

        wrapper.vm.emptyFilter = true;
        await wrapper.vm.$nextTick();

        expect(1).toEqual(wrapper.vm.filterCount);

        wrapper.vm.duplicateFilter = true;
        await wrapper.vm.$nextTick();

        expect(2).toEqual(wrapper.vm.filterCount);
    });

    it('should open clone modal and request cl endpoint', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showDuplicateModal).toBeFalsy();

        wrapper.vm.onDuplicate({ id: 'foo', name: 'bar' });

        expect(wrapper.vm.showDuplicateModal).toBe('foo');

        wrapper.vm.onCloseDuplicateModal();

        expect(wrapper.vm.showDuplicateModal).toBeFalsy();

        wrapper.vm.onConfirmDuplicate('foo');

        expect(cloneEndpoint).toHaveBeenCalledTimes(1);
    });

    it('should open detail modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.onDetail('foo', 'bar', 'baz');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showDetailModal).toBe('foo');
        expect(wrapper.vm.detailProperty).toBe('bar');
        expect(wrapper.vm.detailEntity).toBe('baz');
    });
});
