import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('ct-settings-country-general', {
            sync: true,
        }),
        {
            props: {
                country: {
                    name: 'Germany',
                    position: 1,
                    iso: 'DE',
                    iso3: 'DEU',
                    active: true,
                },
                isLoading: false,
            },
            global: {
                mocks: {
                    $t: (key) => key,
                },
                provide: {
                    acl: {
                        can: (identifier) => privileges.includes(identifier),
                    },
                },
                stubs: {
                    'ct-container': await wrapTestComponent('ct-container'),
                    'mt-number-field': true,
                },
            },
        },
    );
}

describe('module/ct-settings-country/component/ct-settings-country-general', () => {
    it('enables retained country fields for editors', async () => {
        const wrapper = await createWrapper(['country.editor']);

        expect(
            wrapper.find('.mt-text-field input[aria-label="ct-settings-country.detail.labelName"]').attributes().disabled,
        ).toBeUndefined();
        expect(
            wrapper.find('mt-number-field-stub[label="ct-settings-country.detail.labelPosition"]').attributes().disabled,
        ).toBeUndefined();
        expect(
            wrapper.find('.mt-text-field input[aria-label="ct-settings-country.detail.labelIso"]').attributes().disabled,
        ).toBeUndefined();
        expect(
            wrapper.find('.mt-text-field input[aria-label="ct-settings-country.detail.labelIso3"]').attributes().disabled,
        ).toBeUndefined();
        expect(
            wrapper.find('.mt-switch input[aria-label="ct-settings-country.detail.labelActive"]').attributes().disabled,
        ).toBeUndefined();
    });

    it('disables retained country fields without editor privileges', async () => {
        const wrapper = await createWrapper();

        expect(
            wrapper.find('.mt-text-field input[aria-label="ct-settings-country.detail.labelName"]').attributes().disabled,
        ).toBeDefined();
        expect(
            wrapper.find('mt-number-field-stub[label="ct-settings-country.detail.labelPosition"]').attributes().disabled,
        ).toBeDefined();
        expect(
            wrapper.find('.mt-text-field input[aria-label="ct-settings-country.detail.labelIso"]').attributes().disabled,
        ).toBeDefined();
        expect(
            wrapper.find('.mt-text-field input[aria-label="ct-settings-country.detail.labelIso3"]').attributes().disabled,
        ).toBeDefined();
        expect(
            wrapper.find('.mt-switch input[aria-label="ct-settings-country.detail.labelActive"]').attributes().disabled,
        ).toBeDefined();
    });
});
