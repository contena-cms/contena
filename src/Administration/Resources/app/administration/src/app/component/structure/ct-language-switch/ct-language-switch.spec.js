import { mount } from '@vue/test-utils';

describe('src/app/component/structure/ct-language-switch', () => {
    let wrapper = null;

    beforeEach(async () => {
        Contena.Store.get('context').api.languageId = '123456789';

        wrapper = mount(await wrapTestComponent('ct-language-switch', { sync: true }), {
            global: {
                stubs: {
                    'ct-entity-single-select': true,
                    'ct-modal': {
                        template: `
                        <div class="ct-modal-stub">
                            <slot></slot>

                            <div class="modal-footer">
                                <slot name="modal-footer"></slot>
                            </div>
                        </div>
                    `,
                    },
                },
            },
        });
    });

    it('should change the language', async () => {
        Contena.Store.get('context').api.languageId = '123';
        const setApiLanguageId = jest.spyOn(Contena.Store.get('context'), 'setApiLanguageId');

        expect(Contena.Store.get('context').api.languageId).toBe('123');

        wrapper.vm.onInput('456');

        expect(Contena.Store.get('context').api.languageId).toBe('456');
        expect(setApiLanguageId).toHaveBeenCalledWith('456');

        setApiLanguageId.mockRestore();
    });

    it('uses the compact select size used by smart bar actions', () => {
        expect(wrapper.find('#language').attributes('size')).toBe('small');
    });

    it('should open a modal with a warning if abortChangesFunction is set', async () => {
        Contena.Store.get('context').api.languageId = '123';

        await wrapper.setProps({
            abortChangeFunction: () => true,
        });
        await wrapper.vm.onInput('456');

        const modal = wrapper.find('.ct-modal-stub');
        expect(modal.exists()).toBeTruthy();

        expect(wrapper.text()).toContain('ct-language-switch.messageModalUnsavedChanges');

        expect(Contena.Store.get('context').api.languageId).toBe('123');
    });

    it('should revert the changes and set the new language', async () => {
        Contena.Store.get('context').api.languageId = '123';
        const abortChangeMock = jest.fn(() => true);

        await wrapper.setProps({
            abortChangeFunction: abortChangeMock,
        });

        expect(abortChangeMock).not.toHaveBeenCalled();

        await wrapper.vm.onInput('456');

        expect(Contena.Store.get('context').api.languageId).toBe('123');

        expect(abortChangeMock).toHaveBeenCalledWith({
            newLanguageId: '456',
            oldLanguageId: '123456789',
        });

        const revertButton = wrapper.findComponent('#ct-language-switch-revert-changes-button');
        revertButton.vm.$emit('click');

        expect(Contena.Store.get('context').api.languageId).toBe('456');
    });

    it('should save the changes and then set the new language', async () => {
        Contena.Store.get('context').api.languageId = '123';
        const saveChangesMock = jest.fn(() => Promise.resolve());

        await wrapper.setProps({
            abortChangeFunction: () => true,
            saveChangesFunction: saveChangesMock,
        });

        await wrapper.vm.onInput('456');

        expect(Contena.Store.get('context').api.languageId).toBe('123');

        expect(saveChangesMock).not.toHaveBeenCalled();

        const revertButton = wrapper.findComponent('#ct-language-switch-save-changes-button');
        await revertButton.vm.$emit('click');

        expect(saveChangesMock).toHaveBeenCalled();

        expect(Contena.Store.get('context').api.languageId).toBe('456');
    });

    it('should show a warning modal with save button enabled', async () => {
        Contena.Store.get('context').api.languageId = '123';

        await wrapper.setProps({
            abortChangeFunction: () => true,
        });
        await wrapper.vm.onInput('456');

        const saveButton = wrapper.find('#ct-language-switch-save-changes-button');
        expect(saveButton.attributes().disabled).toBeUndefined();
    });

    it('should show a warning modal with save button disabled', async () => {
        Contena.Store.get('context').api.languageId = '123';

        await wrapper.setProps({
            abortChangeFunction: () => true,
            allowEdit: false,
        });
        await wrapper.vm.onInput('456');

        const saveButton = wrapper.find('#ct-language-switch-save-changes-button');
        expect(saveButton.attributes('disabled')).toBeDefined();
    });
});
