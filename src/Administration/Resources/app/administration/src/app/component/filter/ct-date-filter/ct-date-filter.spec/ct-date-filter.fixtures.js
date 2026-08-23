import { mount } from '@vue/test-utils';

export async function createWrapper() {
    return mount(await wrapTestComponent('ct-date-filter', { sync: true }), {
        global: {
            stubs: {
                'ct-base-filter': await wrapTestComponent('ct-base-filter', {
                    sync: true,
                }),
                'ct-range-filter': await wrapTestComponent('ct-range-filter', {
                    sync: true,
                }),
                'mt-select': true,
                'mt-datepicker': {
                    props: ['modelValue'],
                    template: `
                    <div class="ct-field--datepicker">
                        <input type="text" ref="flatpickrInput" :value="modelValue" @input="onChange">
                    </div>`,
                    methods: {
                        onChange(e) {
                            this.$emit('update:modelValue', e.target.value);
                        },
                    },
                },
                'ct-container': {
                    template: '<div class="ct-container"><slot></slot></div>',
                },
            },
        },
        props: {
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
            },
            active: true,
        },
    });
}

/**
 * Registers the lifecycle hooks shared by every ct-date-filter spec:
 * fake timers anchored to 1337-12-31 and a UTC user before each test.
 */
export function setupDateFilterHooks() {
    beforeAll(() => {
        jest.useFakeTimers('modern');
        jest.setSystemTime(new Date(1337, 11, 31));
    });

    beforeEach(() => {
        Contena.Store.get('session').setCurrentUser({ timeZone: 'UTC' });
    });

    afterAll(() => {
        jest.useRealTimers();
    });
}
