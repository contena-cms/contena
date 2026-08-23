/**
 * @ct-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-category-seo-form', { sync: true }), {
        global: {
            stubs: {
                'ct-text-field': true,
                'mt-textarea': true,
            },
        },
        props: {
            category: {},
        },
    });
}

describe('src/module/ct-category/component/ct-category-seo-form', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have an all fields enabled when having the right acl rights', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const textFields = wrapper.findAll('ct-field-stub');

        textFields.forEach((textField) => {
            expect(textField.attributes().disabled).toBeUndefined();
        });
    });

    it('should have an all fields disabled when not having the right acl rights', async () => {
        const wrapper = await createWrapper();

        const textFields = wrapper.findAll('ct-field-stub');

        textFields.forEach((textField) => {
            expect(textField.attributes().disabled).toBe('true');
        });
    });
});
