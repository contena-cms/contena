import { getServicesWithContenaAccountRequirement, serviceHasContenaAccountRequirement } from './index';

describe('src/module/sw-settings-services/requirements', () => {
    it('detects services with the Contena Account requirement', () => {
        expect(serviceHasContenaAccountRequirement(['contena_account'])).toBe(true);
        expect(serviceHasContenaAccountRequirement(['service_consent'])).toBe(false);
        expect(serviceHasContenaAccountRequirement([])).toBe(false);
    });

    it('returns services with the Contena Account requirement', () => {
        expect(
            getServicesWithContenaAccountRequirement([
                {
                    name: 'account-service',
                    label: 'Account Service',
                    requirements: ['contena_account'],
                },
                {
                    name: 'regular-service',
                    label: 'Regular Service',
                    requirements: ['service_consent'],
                },
            ]),
        ).toEqual([
            {
                name: 'account-service',
                label: 'Account Service',
            },
        ]);
    });
});
