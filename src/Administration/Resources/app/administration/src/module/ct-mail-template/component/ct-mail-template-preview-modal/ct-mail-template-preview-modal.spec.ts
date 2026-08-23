import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

const defaultPreview = {
    subject: { type: 'success', content: 'Subject' },
    senderName: { type: 'success', content: 'Sender' },
    headerPlain: { type: 'success', content: 'Header plain' },
    contentPlain: { type: 'success', content: 'Content plain' },
    footerPlain: { type: 'success', content: 'Footer plain' },
    headerHtml: { type: 'success', content: '<div>Header</div>' },
    contentHtml: { type: 'success', content: '<div>Content</div>' },
    footerHtml: { type: 'success', content: '<div>Footer</div>' },
};

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('ct-mail-template-preview-modal', { sync: true }), {
        props: { isLoading: false, mailPreview: defaultPreview, ...props },
        global: {
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                'mt-banner': defineComponent({ template: '<div><slot /></div>' }),
                'mt-button': defineComponent({
                    emits: ['click'],
                    template: '<button @click="$emit(\'click\')"><slot /></button>',
                }),
            },
        },
    });
}

describe('modules/ct-mail-template/component/ct-mail-template-preview-modal', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    it('emits modal-close when the close button is clicked', async () => {
        const wrapper = await createWrapper();
        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('hides preview content while loading', async () => {
        const wrapper = await createWrapper({ isLoading: true });
        expect(wrapper.find('.ct-mail-template-preview-modal__subject').exists()).toBe(false);
        expect(wrapper.findAll('.ct-mail-template-preview-modal__html-content')).toHaveLength(0);
    });

    it('renders html preview content', async () => {
        const wrapper = await createWrapper();
        const htmlContents = wrapper.findAll('.ct-mail-template-preview-modal__html-content');
        expect(htmlContents).toHaveLength(3);
        expect(htmlContents.at(0)?.html()).toContain('<div>Header</div>');
    });

    it('renders error banners instead of success content for error branches', async () => {
        const wrapper = await createWrapper({
            mailPreview: {
                ...defaultPreview,
                subject: { type: 'error', errorTitle: 'Twig syntax error', errorMessage: 'subject failed.' },
                contentPlain: { type: 'error', errorTitle: 'Twig syntax error', errorMessage: 'plain failed.' },
                contentHtml: { type: 'error', errorTitle: 'Twig syntax error', errorMessage: 'html failed.' },
            },
        });

        expect(wrapper.find('.ct-mail-template-preview-modal__subject-error').exists()).toBe(true);
        expect(wrapper.find('.ct-mail-template-preview-modal__subject-content').exists()).toBe(false);
        expect(wrapper.findAll('.ct-mail-template-preview-modal__plain-text-error')).toHaveLength(1);
        expect(wrapper.findAll('.ct-mail-template-preview-modal__html-error')).toHaveLength(1);
        expect(wrapper.findAll('.ct-mail-template-preview-modal__html-content')).toHaveLength(2);
    });
});
