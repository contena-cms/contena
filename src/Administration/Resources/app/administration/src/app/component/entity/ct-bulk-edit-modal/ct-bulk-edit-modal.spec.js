import { mount } from '@vue/test-utils';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/user-config',
    status: 200,
    response: { data: [] },
});

describe('src/app/component/entity/ct-bulk-edit-modal', () => {
    let wrapper;
    let stubs;

    const modal = async () => {
        return mount(
            await wrapTestComponent('ct-bulk-edit-modal', {
                sync: true,
            }),
            {
                props: {
                    selection: {
                        uuid1: {
                            id: 'uuid1',
                            manufacturer: 'Wordify',
                            name: 'Portia Jobson',
                        },
                        uuid2: {
                            id: 'uuid2',
                            manufacturer: 'Twitternation',
                            name: 'Baxy Eardley',
                        },
                        uuid3: {
                            id: 'uuid3',
                            manufacturer: 'Skidoo',
                            name: 'Arturo Staker',
                        },
                    },
                    bulkGridEditColumns: [],
                },
                global: {
                    renderStubDefaultSlot: true,
                    stubs: stubs,
                    data() {
                        return {};
                    },
                },
            },
        );
    };

    beforeAll(async () => {
        stubs = {
            'ct-block': {
                inheritAttrs: false,
                props: [
                    'name',
                    'data',
                ],
                template: '<div><slot></slot></div>',
            },
            'ct-modal': await wrapTestComponent('ct-modal'),
            'ct-data-grid': {
                template: '<div class="ct-data-grid"><slot></slot><slot name="pagination"></slot></div>',
            },
            'ct-pagination': await wrapTestComponent('ct-pagination'),
            'ct-loader': true,
            'ct-context-menu-item': true,
            'ct-context-button': true,
            'ct-data-grid-settings': true,
            'ct-data-grid-column-boolean': true,
            'ct-data-grid-inline-edit': true,
            'router-link': true,
            'ct-data-grid-skeleton': true,
            'ct-provide': true,
        };
    });

    it('initializes records from the selection', async () => {
        wrapper = await modal();

        expect(wrapper.vm.records).toHaveLength(3);
        expect(wrapper.vm.records.map(({ id }) => id)).toEqual([
            'uuid1',
            'uuid2',
            'uuid3',
        ]);
    });

    it('uses the initial pagination state', async () => {
        wrapper = await modal();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.limit).toBe(200);
    });

    it('updates the pagination state', async () => {
        wrapper = await modal();

        wrapper.vm.paginate({ page: 2, limit: 10 });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(10);
    });

    it('paginates the initialized records', async () => {
        wrapper = await modal();

        expect(wrapper.vm.paginateRecords).toHaveLength(3);
    });
});
