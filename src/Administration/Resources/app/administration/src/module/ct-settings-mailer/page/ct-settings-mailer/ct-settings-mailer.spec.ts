import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import '../../index';

const systemConfigApiService = {
    getValues: jest.fn(),
    saveValues: jest.fn(),
};

async function createWrapper(values: Record<string, unknown> = {}) {
    systemConfigApiService.getValues.mockResolvedValue(values);
    systemConfigApiService.saveValues.mockResolvedValue(undefined);

    const wrapper = mount(await wrapTestComponent('ct-settings-mailer', { sync: true }), {
        global: {
            provide: { systemConfigApiService },
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'ct-page': defineComponent({
                    template: '<div><slot name="smart-bar-actions" /><slot name="content" /></div>',
                }),
                'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                'ct-button-process': true,
                'mt-card': defineComponent({ template: '<div><slot /></div>' }),
                'mt-select': true,
                'mt-text-field': true,
                'mt-number-field': true,
                'mt-password-field': true,
                'mt-switch': true,
            },
        },
    });
    await flushPromises();

    return wrapper;
}

describe('module/ct-settings-mailer/page/ct-settings-mailer', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('selects the SMTP server and shows its settings by default', async () => {
        const wrapper = await createWrapper();
        const page = wrapper.vm as unknown as {
            mailerSettings: Record<string, unknown>;
        };

        expect(page.mailerSettings['core.mailerSettings.emailAgent']).toBe('smtp');
        expect(wrapper.find('.ct-settings-mailer__smtp-grid').exists()).toBe(true);
    });

    it('loads the saved SMTP mode and renders its settings', async () => {
        const wrapper = await createWrapper({
            'core.mailerSettings.emailAgent': 'smtp',
            'core.mailerSettings.host': 'smtp.example.com',
        });

        expect(systemConfigApiService.getValues).toHaveBeenCalledWith('core.mailerSettings');
        expect(wrapper.find('.ct-settings-mailer__smtp-grid').exists()).toBe(true);
    });

    it('does not save an incomplete SMTP configuration', async () => {
        const wrapper = await createWrapper();
        const page = wrapper.vm as unknown as {
            mailerSettings: Record<string, unknown>;
            onSave: () => Promise<void>;
        };
        page.mailerSettings['core.mailerSettings.emailAgent'] = 'smtp';

        await page.onSave();

        expect(systemConfigApiService.saveValues).not.toHaveBeenCalled();
    });

    it('saves the environment option as an empty agent for the backend fallback', async () => {
        const wrapper = await createWrapper();
        const page = wrapper.vm as unknown as {
            mailerSettings: Record<string, unknown>;
            onSave: () => Promise<void>;
            isSaveSuccessful: boolean;
        };
        page.mailerSettings['core.mailerSettings.emailAgent'] = 'environment';

        await page.onSave();

        expect(systemConfigApiService.saveValues).toHaveBeenCalledWith(
            expect.objectContaining({ 'core.mailerSettings.emailAgent': '' }),
        );
        expect(page.isSaveSuccessful).toBe(true);
    });
});
