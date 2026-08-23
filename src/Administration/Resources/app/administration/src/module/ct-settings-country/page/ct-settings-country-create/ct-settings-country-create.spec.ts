import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

describe('module/ct-settings-country/page/ct-settings-country-create', () => {
    it('creates the country from the inherited repository factory', async () => {
        const reservedPrefixWarning = {
            method: 'warn',
            msg: 'setup() return property "$route" should not start with "$" or "_"',
        };
        const allowedErrors = (
            globalThis as typeof globalThis & {
                allowedErrors: Array<{ method: string; msg: string }>;
            }
        ).allowedErrors;
        allowedErrors.push(reservedPrefixWarning);

        const country = {
            id: 'country-id',
            name: null,
        };
        const countryRepository = {
            create: jest.fn(() => country),
            get: jest.fn(() => Promise.resolve(country)),
        };
        const repositoryFactory = {
            create: jest.fn(() => countryRepository),
        };

        const wrapper = mount(await wrapTestComponent('ct-settings-country-create', { sync: true }), {
            global: {
                provide: {
                    [routeLocationKey as symbol]: {
                        fullPath: '/sw/settings/country/create/country-id/general',
                        name: 'ct.settings.country.create.general',
                        params: { id: 'country-id' },
                    },
                    [routerKey as symbol]: { push: jest.fn() },
                    repositoryFactory,
                    acl: { can: () => true },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
                stubs: {
                    'ct-page': true,
                },
            },
        });
        allowedErrors.pop();

        await flushPromises();

        expect(countryRepository.create).toHaveBeenCalledWith(Contena.Context.api, 'country-id');
        expect(repositoryFactory.create).toHaveBeenCalledWith('country');
        expect(wrapper.vm.country).toEqual(country);

        wrapper.unmount();
    });
});
