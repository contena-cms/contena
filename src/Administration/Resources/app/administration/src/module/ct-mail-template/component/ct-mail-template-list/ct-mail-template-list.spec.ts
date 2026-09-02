import { defineComponent, toRaw } from 'vue';
import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-mail-template/component/ct-mail-template-list', () => {
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

    it('loads templates with their type association', async () => {
        const criteria = new Contena.Data.Criteria(1, 25);
        const result = new Contena.Data.EntityCollection(
            '/mail-template',
            'mail_template',
            Contena.Context.api,
            criteria,
            [],
            0,
        );
        result.push({ id: 'template-id' } as Entity<'mail_template'>);
        const repository = {
            search: jest.fn((criteriaToSearch: { associations: Array<{ association: string }> }) => {
                expect(criteriaToSearch).toBeDefined();

                return Promise.resolve(result);
            }),
            clone: jest.fn(() => Promise.resolve({ id: 'duplicate-template-id' })),
        };
        const router = { push: jest.fn(() => Promise.resolve()) };
        const entityListing = defineComponent({
            name: 'CtEntityListing',
            props: [
                'allowEdit',
                'allowDelete',
            ],
            setup() {
                return { item: { id: 'template-id' } };
            },
            template: '<div class="entity-listing"><slot name="more-actions" :item="item" /></div>',
        });

        const wrapper = mount(await wrapTestComponent('ct-mail-template-list', { sync: true }), {
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
                    'mt-icon': true,
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
        const requestedCriteria = repository.search.mock.calls[0]?.[0];
        expect(requestedCriteria?.associations).toEqual(
            expect.arrayContaining([expect.objectContaining({ association: 'mailTemplateType' })]),
        );
        expect(wrapper.vm.columns).toEqual([
            expect.objectContaining({
                property: 'mailTemplateType.name',
                routerLink: 'ct.mail.template.detail',
                primary: true,
            }),
            expect.objectContaining({ property: 'description' }),
        ]);
        expect(toRaw(wrapper.vm.templates)).toBe(result);
        expect(wrapper.vm.showListing).toBe(true);
        expect(wrapper.vm.skeletonItemAmount).toBe(1);

        const listing = wrapper.findComponent(entityListing);
        expect(listing.props()).toMatchObject({ allowEdit: true, allowDelete: true });
        await wrapper.find('.ct-mail-template-list-grid__duplicate-action').trigger('click');
        await flushPromises();

        expect(repository.clone).toHaveBeenCalledWith('template-id');
        expect(router.push).toHaveBeenLastCalledWith({
            name: 'ct.mail.template.detail',
            params: { id: 'duplicate-template-id' },
        });

        const emptyResult = new Contena.Data.EntityCollection(
            '/mail-template',
            'mail_template',
            Contena.Context.api,
            criteria,
            [],
            0,
        );
        const component = wrapper.vm as unknown as {
            updateRecords: (result: EntityCollection<'mail_template'>) => void;
            showListing: boolean;
            skeletonItemAmount: number;
        };
        component.updateRecords(emptyResult);
        expect(component.showListing).toBe(false);
        expect(component.skeletonItemAmount).toBe(3);
    });
});
