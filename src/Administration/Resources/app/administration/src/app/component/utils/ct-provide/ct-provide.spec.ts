import { mount } from '@vue/test-utils';

async function createWrapper({ template = '<ct-provide />', components = {}, data = {} } = {}) {
    return mount({
        template,
        components: {
            'ct-provide': await wrapTestComponent('ct-provide', { sync: true }),
            ...components,
        },
        data() {
            return data;
        },
    });
}

describe('src/app/component/base/ct-provide', () => {
    it('renders the children without adding extra HTML', async () => {
        const wrapper = await createWrapper({
            template: `
            <ct-provide>
                <div class="test-child" />
            </ct-provide>`,
        });

        expect(wrapper.html()).toBe('<div class="test-child"></div>');
    });

    it('provides the attributes to the children', async () => {
        const wrapper = await createWrapper({
            template: `
            <ct-provide :foo="42" :bar="true">
                    <child-component>{{ foo }} {{ bar }}</child-component>
            </ct-provide>`,
            components: {
                'child-component': {
                    template: '<div>{{ foo }} {{ bar }}</div>',
                    inject: [
                        'foo',
                        'bar',
                    ],
                },
            },
        });

        expect(wrapper.text()).toBe('42 true');
    });

    it('keeps reactivity of provided attributes', async () => {
        const wrapper = await createWrapper({
            template: `
            <ct-provide :foo="foo">
                <child-component>{{ foo }}</child-component>
            </ct-provide>`,
            components: {
                'child-component': {
                    template: '<div>{{ foo }}</div>',
                    inject: ['foo'],
                },
            },
            data: {
                foo: 'bar',
            },
        });

        expect(wrapper.text()).toBe('bar');
        await wrapper.setData({ foo: 'baz' });
        expect(wrapper.text()).toBe('baz');
    });

    it('converts attrs name to camelCase', async () => {
        const wrapper = await createWrapper({
            template: `
            <ct-provide :foo-bar="42" :barFoo="true">
                <child-component>{{ fooBar }}</child-component>
            </ct-provide>`,
            components: {
                'child-component': {
                    template: '<div>{{ fooBar }} {{ barFoo }}</div>',
                    inject: [
                        'fooBar',
                        'barFoo',
                    ],
                },
            },
        });

        expect(wrapper.text()).toBe('42 true');
    });
});
