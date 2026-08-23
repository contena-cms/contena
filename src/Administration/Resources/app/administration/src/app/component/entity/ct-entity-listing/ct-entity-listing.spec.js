import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(propsData = {}) {
    // mock entity functions
    const items = [
        { name: 'Apple' },
        { name: 'Contena' },
        { name: 'Google' },
        { name: 'Microsoft' },
    ];
    items.total = 4;
    items.criteria = {
        page: null,
        limit: null,
    };

    const wrapper = mount(await wrapTestComponent('ct-entity-listing', { sync: true }), {
        props: {
            columns: [
                { property: 'name', label: 'Name' },
            ],
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
            ]),
            repository: {
                search: () => {},
            },
            detailRoute: 'ct.manufacturer.detail',
            ...propsData,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'ct-data-grid-settings': await wrapTestComponent('ct-data-grid-settings'),
                'ct-context-button': true,
                'ct-field': true,

                'ct-context-menu-divider': true,
                'ct-pagination': true,
                'ct-checkbox-field': true,
                'ct-context-menu-item': true,
                'ct-data-grid-skeleton': true,
                'ct-bulk-edit-modal': true,
                'ct-data-grid-column-boolean': true,
                'ct-data-grid-inline-edit': true,
                'router-link': true,
                'ct-button-group': true,
                'ct-provide': true,
            },
        },
    });

    return wrapper;
}

describe('src/app/component/entity/ct-entity-listing', () => {
    it('should enable the context menu edit item', async () => {
        const wrapper = await createWrapper();

        const firstRow = wrapper.find('.ct-data-grid__row--1');
        const firstRowActions = firstRow.find('.ct-data-grid__cell--actions');
        const firstRowActionEdit = firstRowActions.find('.ct-entity-listing__context-menu-edit-action');

        expect(firstRowActionEdit.exists()).toBeTruthy();
        expect(firstRowActionEdit.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu edit item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowEdit: false,
        });

        const firstRow = wrapper.find('.ct-data-grid__row--1');
        const firstRowActions = firstRow.find('.ct-data-grid__cell--actions');
        const firstRowActionEdit = firstRowActions.find('.ct-entity-listing__context-menu-edit-action');

        expect(firstRowActionEdit.exists()).toBeTruthy();
        expect(firstRowActionEdit.attributes().disabled).toBeTruthy();
    });

    it('should enable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        const firstRow = wrapper.find('.ct-data-grid__row--1');
        const firstRowActions = firstRow.find('.ct-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.ct-entity-listing__context-menu-edit-delete');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDelete: false,
        });

        const firstRow = wrapper.find('.ct-data-grid__row--1');
        const firstRowActions = firstRow.find('.ct-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.ct-entity-listing__context-menu-edit-delete');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeTruthy();
    });

    it('should have context menu with edit entry', async () => {
        const wrapper = await createWrapper({
            allowEdit: true,
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        const elements = wrapper.findAll('.ct-entity-listing__context-menu-edit-action');

        elements.forEach((el) => expect(el.text()).toBe('global.default.edit'));
        expect(elements).toHaveLength(3);
    });

    it('should have context menu with view entry', async () => {
        const wrapper = await createWrapper({
            allowEdit: false,
            allowView: true,
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        const elements = wrapper.findAll('.ct-entity-listing__context-menu-edit-action');

        elements.forEach((el) => expect(el.text()).toBe('global.default.view'));
        expect(elements).toHaveLength(3);
    });

    it('should have context menu with disabled edit entry', async () => {
        const wrapper = await createWrapper({
            allowEdit: false,
            allowView: false,
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });
        await flushPromises();

        const elements = wrapper.findAll('.ct-entity-listing__context-menu-edit-action');

        expect(elements).toHaveLength(3);
        elements.forEach((el) => expect(el.text()).toBe('global.default.edit'));
        elements.forEach((el) => expect(el.attributes().disabled).toBe('true'));
    });

    it('should show delete id', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.deleteId).toBeNull();
        wrapper.vm.showDelete('123');
        expect(wrapper.vm.deleteId).toBe('123');
    });

    it('should refresh delete id when close delete modal', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.showDelete('123');
        expect(wrapper.vm.deleteId).toBe('123');
        wrapper.vm.closeModal();
        expect(wrapper.vm.deleteId).toBeNull();
    });

    it('should call emit when user click bulk edit button', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onClickBulkEdit();

        await flushPromises();
        expect(wrapper.emitted('bulk-edit-modal-open')).toStrictEqual([[]]);
    });

    it('should call emit when user close bulk edit modal', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onCloseBulkEditModal();

        await flushPromises();
        expect(wrapper.emitted('bulk-edit-modal-close')).toStrictEqual([[]]);
    });

    it('should work with the dataSource prop', async () => {
        const dataSource = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
            { id: 'id2', name: 'item2' },
        ]);

        const wrapper = await createWrapper({
            dataSource,
        });

        expect(wrapper.vm.dataSource).toHaveLength(2);
        expect(wrapper.vm.dataSource[0].id).toBe('id1');
        expect(wrapper.vm.dataSource[1].id).toBe('id2');
        expect(wrapper.vm.records).toHaveLength(2);
    });

    it('should apply result when dataSource prop has been changed', async () => {
        const wrapper = await createWrapper({
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
            ]),
        });

        await wrapper.setProps({
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        await flushPromises();
        expect(Array.from(wrapper.vm.records, ({ id }) => id)).toEqual([
            'id2',
            'id3',
        ]);
        expect(wrapper.emitted('update-records')).toHaveLength(2);
    });

    it('should use dataSource for operations', async () => {
        const dataSource = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
        ]);
        dataSource.context = { apiContext: true };
        dataSource.criteria = new Criteria(1, 25);

        const mockSearchResult = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
        ]);

        const wrapper = await createWrapper({
            dataSource,
            repository: {
                search: jest.fn(() => Promise.resolve(mockSearchResult)),
                delete: jest.fn(() => Promise.resolve()),
                save: jest.fn(() => Promise.resolve()),
            },
        });

        // Test that doSearch uses dataSource
        await wrapper.vm.doSearch();
        expect(wrapper.vm.repository.search).toHaveBeenCalledWith(dataSource.criteria, dataSource.context);
    });
});
