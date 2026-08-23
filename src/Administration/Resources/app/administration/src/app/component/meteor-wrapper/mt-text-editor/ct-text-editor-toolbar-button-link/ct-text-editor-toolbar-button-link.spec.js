import { shallowMount } from '@vue/test-utils';

const seoUrlPrefix = '124c71d524604ccbad6042edce3ac799';

function createEditorStub(linkAttributes = {}) {
    const editor = {
        getAttributes: jest.fn(() => ({
            href: 'https://example.com',
            target: null,
            ...linkAttributes,
        })),
        isActive: jest.fn(() => false),
        chain: jest.fn(() => editor),
        focus: jest.fn(() => editor),
        extendMarkRange: jest.fn(() => editor),
        setLink: jest.fn(() => editor),
        unsetLink: jest.fn(() => editor),
        run: jest.fn(() => editor),
    };

    return editor;
}

async function createWrapper(editor = createEditorStub()) {
    return shallowMount(await wrapTestComponent('ct-text-editor-toolbar-button-link', { sync: true }), {
        props: {
            editor,
            button: {
                label: 'Link',
            },
        },
    });
}

describe('app/component/meteor-wrapper/mt-text-editor/ct-text-editor-toolbar-button-link', () => {
    it.each([
        [
            'link',
            'https://example.com',
            'link',
            'https://example.com',
        ],
        [
            'email',
            'mailto:test@example.com',
            'email',
            'test@example.com',
        ],
        [
            'phone',
            'tel:+4912345',
            'phone',
            '+4912345',
        ],
        [
            'media',
            `${seoUrlPrefix}/mediaId/media-id#`,
            'media',
            'media-id',
        ],
    ])('opens %s links in the matching editor mode', async (type, href, expectedType, expectedHref) => {
        const wrapper = await createWrapper(createEditorStub({ href }));

        wrapper.vm.openLinkModal();

        expect(wrapper.vm.showLinkModal).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.linkType).toBe(expectedType);
        expect(wrapper.vm.linkHref).toBe(expectedHref);
    });

    it.each([
        [
            'link',
            'example.com',
            'https://example.com',
        ],
        [
            'email',
            'test@example.com',
            'mailto:test@example.com',
        ],
        [
            'phone',
            '+49/12345',
            'tel:+4912345',
        ],
        [
            'media',
            'media-id',
            `${seoUrlPrefix}/mediaId/media-id#`,
        ],
    ])('applies prepared %s links', async (type, href, expectedHref) => {
        const editor = createEditorStub();
        const wrapper = await createWrapper(editor);
        Object.assign(wrapper.vm, {
            linkType: type,
            linkHref: href,
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.applyLink();

        expect(editor.setLink).toHaveBeenCalledWith({
            href: expectedHref,
            target: null,
            class: undefined,
        });
        expect(wrapper.vm.showLinkModal).toBe(false);
    });

    it('removes a link from the current selection', async () => {
        const editor = createEditorStub();
        const wrapper = await createWrapper(editor);

        wrapper.vm.removeLink();

        expect(editor.unsetLink).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.showLinkModal).toBe(false);
    });

    it('clears the href when the link type changes', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.linkHref = 'https://example.com';
        await wrapper.vm.$nextTick();

        wrapper.vm.onSelectFieldChange('email');

        expect(wrapper.vm.linkType).toBe('email');
        expect(wrapper.vm.linkHref).toBe('');
    });
});
