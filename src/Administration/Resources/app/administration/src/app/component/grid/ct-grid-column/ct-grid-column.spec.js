import { mount } from '@vue/test-utils';

const defaultProps = {
    label: 'Test column',
};

async function createWrapper(props = defaultProps, ctGridColumns = []) {
    return mount(await wrapTestComponent('ct-grid-column', { sync: true }), {
        props,
        global: {
            stubs: {},
            provide: {
                ctGridColumns,
            },
        },
    });
}

describe('components/grid/ct-grid-column', () => {
    it.each([
        { name: 'to true if label is missing', label: null, expected: 1 },
        { name: 'to false if label is provided', label: 'Test column', expected: 1 },
    ])('should set spacer option $name', async ({ label, expected }) => {
        const ctGridColumns = [];
        await createWrapper({ label }, ctGridColumns);

        expect(ctGridColumns).toHaveLength(expected);
    });
});
