import { defineComponent, toRaw } from 'vue';
import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-mail-template/component/ct-mail-header-footer-list', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    it('loads headers and footers with the upstream primary name and creation date columns', async () => {
        const criteria = new Contena.Data.Criteria(1, 25);
        const result = new Contena.Data.EntityCollection(
            '/mail-header-footer',
            'mail_header_footer',
            Contena.Context.api,
            criteria,
            [],
            0,
        );
        result.push({ id: 'header-footer-id' } as Entity<'mail_header_footer'>);
        const repository = {
            search: jest.fn(() => Promise.resolve(result)),
            clone: jest.fn(() => Promise.resolve({ id: 'duplicate-header-footer-id' })),
        };
        const router = { push: jest.fn(() => Promise.resolve()) };
        const entityListing = defineComponent({
            name: 'CtEntityListing',
            props: [
                'allowEdit',
                'allowDelete',
                'detailRoute',
            ],
            setup() {
                return { item: { id: 'header-footer-id' } };
            },
            template: '<div class="entity-listing"><slot name="more-actions" :item="item" /></div>',
        });

        const wrapper = mount(await wrapTestComponent('ct-mail-header-footer-list', { sync: true }), {
            global: {
                provide: {
                    repositoryFactory: { create: jest.fn(() => repository) },
                    acl: { can: jest.fn(() => true) },
                    [routeLocationKey as symbol]: { query: {} },
                    [routerKey as symbol]: router,
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<div><slot name="grid" /></div>' }),
                    'ct-entity-listing': entityListing,
                    'ct-time-ago': true,
                    'ct-context-menu-item': defineComponent({
                        props: ['disabled'],
                        emits: ['click'],
                        template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                    }),
                },
            },
        });
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.columns).toEqual([
            expect.objectContaining({
                property: 'name',
                routerLink: 'ct.mail.template.detail_head_foot',
                primary: true,
            }),
            expect.objectContaining({ property: 'description' }),
        ]);
        expect(toRaw(wrapper.vm.items)).toBe(result);
        expect(wrapper.vm.showListing).toBe(true);
        expect(wrapper.vm.skeletonItemAmount).toBe(1);

        const listing = wrapper.findComponent(entityListing);
        expect(listing.props()).toMatchObject({
            allowEdit: true,
            allowDelete: true,
            detailRoute: 'ct.mail.template.detail_head_foot',
        });
        await wrapper.find('.ct-mail-header-footer-list-grid__duplicate-action').trigger('click');
        await flushPromises();

        expect(repository.clone).toHaveBeenCalledWith('header-footer-id');
        expect(router.push).toHaveBeenLastCalledWith({
            name: 'ct.mail.template.detail_head_foot',
            params: { id: 'duplicate-header-footer-id' },
        });

        const emptyResult = new Contena.Data.EntityCollection(
            '/mail-header-footer',
            'mail_header_footer',
            Contena.Context.api,
            criteria,
            [],
            0,
        );
        const component = wrapper.vm as unknown as {
            updateRecords: (result: EntityCollection<'mail_header_footer'>) => void;
            showListing: boolean;
            skeletonItemAmount: number;
        };
        component.updateRecords(emptyResult);
        expect(component.showListing).toBe(false);
        expect(component.skeletonItemAmount).toBe(3);
    });
});
