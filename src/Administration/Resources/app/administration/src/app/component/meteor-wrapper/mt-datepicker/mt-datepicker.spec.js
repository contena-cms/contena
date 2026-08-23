import { mount } from '@vue/test-utils';

describe('src/app/component/meteor-wrapper/mt-datepicker', () => {
    beforeAll(() => {
        Contena.Store.get('system').registerAdminLocale('zh-CN');
        Contena.Store.get('system').registerAdminLocale('en-GB');
    });

    beforeEach(() => {
        Contena.Store.get('session').setCurrentUser({
            firstName: 'John',
            lastName: 'Doe',
            timeZone: 'Europe/Berlin',
        });

        Contena.Store.get('session').setAdminLocale('zh-CN');
    });

    it('should use the user timeZone', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        expect(wrapper.find('[data-testid="time-zone-hint"]').text()).toBe('Europe/Berlin');
    });

    it('should default to Asia/Shanghai without a user timeZone', async () => {
        Contena.Store.get('session').setCurrentUser(null);

        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        expect(wrapper.find('[data-testid="time-zone-hint"]').text()).toBe('Asia/Shanghai');
    });

    it('should use the user locale (zh-CN)', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');
        await flushPromises();

        expect(document.body.textContent).toContain('一二三四五六日');
    });

    it('should use the user locale (en)', async () => {
        // Set the user locale to english
        Contena.Store.get('session').setAdminLocale('en-GB');

        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }));

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');
        await flushPromises();

        expect(document.body.textContent).toContain('MoTuWeThFrSaSu');
    });

    it('should use custom format based on currentLocale (zh-CN)', async () => {
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                modelValue: '2023-10-01T00:00:00+02:00',
            },
        });

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');

        expect(wrapper.find('[data-test-id="dp-input"]').element.value).toBe('2023/10/01 00:00');
    });

    it('should use custom format based on currentLocale (en)', async () => {
        // Set the user locale to english
        Contena.Store.get('session').setAdminLocale('en-GB');

        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                modelValue: '2023-10-01T00:00:00+02:00',
            },
        });

        // Click on input to open datepicker
        await wrapper.find('[data-test-id="dp-input"]').trigger('click');

        expect(wrapper.find('[data-test-id="dp-input"]').element.value).toBe('01/10/2023, 00:00');
    });

    it('should escape literal text in generated date-fns format', async () => {
        const dateTimeFormatMock = jest.spyOn(Intl, 'DateTimeFormat').mockImplementation(() => ({
            resolvedOptions: () => ({ hour12: false }),
            formatToParts: () => [
                { type: 'hour', value: '14' },
                { type: 'literal', value: ' h ' },
                { type: 'minute', value: '30' },
                { type: 'literal', value: " o'clock " },
                { type: 'dayPeriod', value: 'PM' },
            ],
        }));
        const wrapper = mount(await wrapTestComponent('mt-datepicker', { sync: true }), {
            props: {
                format: jest.fn(),
            },
        });

        expect(wrapper.vm.datePickerFormat).toBe("HH' h 'mm' o''clock 'aa");

        dateTimeFormatMock.mockRestore();
    });
});
