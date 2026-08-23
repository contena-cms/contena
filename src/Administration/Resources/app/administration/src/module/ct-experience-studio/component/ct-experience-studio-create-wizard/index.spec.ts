/* eslint-disable @typescript-eslint/no-unsafe-call */
import { shallowMount } from '@vue/test-utils';
import createWizardComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-create-wizard', () => {
    it('is completable only with trimmed name and selected type', async () => {
        const wrapper = shallowMount(createWizardComponent, {
            props: { name: ' My layout ', selectedType: 'blog' },
        });

        expect(wrapper.vm.isCompletable).toBe(true);

        await wrapper.setProps({ name: '' });
        expect(wrapper.vm.isCompletable).toBe(false);

        await wrapper.setProps({ name: 'My layout', selectedType: null });
        expect(wrapper.vm.isCompletable).toBe(false);

        await wrapper.setProps({ selectedType: 'blog', isLoadingTypes: true });
        expect(wrapper.vm.isCompletable).toBe(false);
    });

    it('emits complete payload with normalized values', () => {
        const wrapper = shallowMount(createWizardComponent, {
            props: { name: ' My layout ', selectedType: 'category' },
        });

        wrapper.vm.onComplete();

        expect(wrapper.emitted('complete')).toEqual([[{ name: 'My layout', type: 'category' }]]);
    });
});
