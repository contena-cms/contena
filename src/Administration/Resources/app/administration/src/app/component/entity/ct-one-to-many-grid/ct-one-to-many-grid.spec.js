import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-one-to-many-grid', { sync: true }), {
        props: {
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                },
                {
                    property: 'shortCode',
                    label: 'Short code',
                },
            ],
            collection: [
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
            ],
            allowDelete: true,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return Promise.resolve({
                            total: 0,
                            criteria: {
                                page: 1,
                                limit: 25,
                            },
                        });
                    },
                },
            },
            renderStubDefaultSlot: true,
            stubs: {
                'ct-pagination': true,
                'ct-checkbox-field': true,
                'ct-context-button': true,
                'ct-context-menu-item': true,
                'ct-data-grid-settings': true,
                'ct-data-grid-column-boolean': true,
                'ct-data-grid-inline-edit': true,
                'router-link': true,
                'ct-data-grid-skeleton': true,
                'ct-provide': true,
            },
        },
    });
}

describe('app/component/entity/ct-one-to-many-grid', () => {
    it('should enable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        Object.assign(wrapper.vm, {
            records: [
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
            ],
        });
        await wrapper.vm.$nextTick();

        const firstRow = wrapper.find('.ct-data-grid__row--1');
        const firstRowActions = firstRow.find('.ct-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.ct-one-to-many-grid__delete-action');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDelete: false,
        });

        Object.assign(wrapper.vm, {
            records: [
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
                {
                    name: 'name',
                    shortCode: 'shortCode',
                },
            ],
        });
        await wrapper.vm.$nextTick();

        const firstRow = wrapper.find('.ct-data-grid__row--1');
        const firstRowActions = firstRow.find('.ct-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.ct-one-to-many-grid__delete-action');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeTruthy();
    });
});
