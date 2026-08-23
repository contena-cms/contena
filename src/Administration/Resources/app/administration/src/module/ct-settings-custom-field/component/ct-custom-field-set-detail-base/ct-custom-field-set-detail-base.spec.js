import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

function getFieldTypes() {
    return {
        checkbox: {
            config: {
                componentName: 'ct-field',
                type: 'checkbox',
            },
            configRenderComponent: 'ct-custom-field-type-checkbox',
        },
    };
}

async function createWrapper(privileges = [], set = { _isNew: true }) {
    const i18n = createI18n({
        legacy: false,
        locale: 'en-GB',
        fallbackLocale: 'en-GB',
        missingWarn: false,
        fallbackWarn: false,
        messages: {
            'en-GB': {},
            'de-DE': {},
            en: {},
            de: {},
        },
    });

    return mount(
        await wrapTestComponent('ct-custom-field-set-detail-base', {
            sync: true,
        }),
        {
            props: {
                set,
            },
            global: {
                renderStubDefaultSlot: true,
                plugins: [i18n],
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getTypes: () => getFieldTypes(),
                    },
                },
                stubs: {
                    'ct-container': true,
                    'ct-custom-field-type-checkbox': true,
                    'ct-text-field': true,
                    'mt-select': true,
                    'ct-loader': true,

                    'ct-custom-field-translated-labels': true,
                },
            },
        },
    );
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-set-detail-base', () => {
    it('can edit fields', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const technicalNameField = wrapper.findComponent('.ct-settings-custom-field-set-detail-base__technical-name');
        const positionField = wrapper.find('.ct-settings-custom-field-set-detail-base__base-postion');
        const entitiesField = wrapper.find('.ct-settings-custom-field-set-detail-base__label-entities');

        expect(technicalNameField.props('disabled')).toBeFalsy();
        expect(positionField.attributes('disabled')).toBeFalsy();
        expect(entitiesField.attributes('disabled')).toBeFalsy();
    });

    it('only exposes full locale codes as label tabs for translated sets', async () => {
        const wrapper = await createWrapper(['custom_field.editor'], {
            _isNew: true,
            config: { translated: true },
        });
        await flushPromises();

        // short aliases (en, de) leak into vue-i18n messages but must not become editable tabs
        expect(wrapper.vm.locales).toEqual([
            'en-GB',
            'de-DE',
        ]);
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();

        const technicalNameField = wrapper.findComponent('.ct-settings-custom-field-set-detail-base__technical-name');
        const positionField = wrapper.findByLabel('ct-settings-custom-field.set.detail.labelPosition');
        const entitiesField = wrapper.find('.ct-settings-custom-field-set-detail-base__label-entities');

        expect(technicalNameField.props('disabled')).toBeTruthy();
        expect(positionField.attributes('disabled')).toBeDefined();
        expect(entitiesField.attributes('disabled')).toBeDefined();
    });
});
