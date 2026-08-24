import { mount } from '@vue/test-utils';
import component from './index';

describe('src/app/component/base/ct-icon', () => {
    it('resolves any Ant Design Vue icon by its official name', () => {
        const wrapper = mount(component, {
            props: { name: 'ExperimentOutlined' },
        });

        expect(wrapper.find('.anticon-experiment').exists()).toBe(true);
        wrapper.unmount();
    });

    it('resolves kebab-case Ant Design Vue icon names', () => {
        const wrapper = mount(component, {
            props: { name: 'cloud-upload' },
        });

        expect(wrapper.find('.anticon-cloud-upload').exists()).toBe(true);
        wrapper.unmount();
    });
});
