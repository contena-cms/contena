import { mount } from '@vue/test-utils';
import component from './index';

function createWrapper({
    props,
    privileges = [],
    platform = 'Linux x86_64',
}: {
    props: { title: string; content: string; privilege?: string };
    privileges?: string[];
    platform?: string;
}) {
    Object.defineProperty(window.navigator, 'platform', { value: platform, configurable: true });

    return mount(component, {
        props,
        global: {
            provide: {
                acl: {
                    can: (key?: string) => !key || privileges.includes(key),
                },
            },
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('app/component/utils/ct-shortcut-overview-item', () => {
    it('shows the shortcut overview item', () => {
        const wrapper = createWrapper({ props: { title: 'Clear cache', content: 'ALT-C' } });
        const shortcut = wrapper.findAll('kbd');

        expect(shortcut).toHaveLength(2);
        expect(shortcut.at(0)?.text()).toBe('Alt');
        expect(shortcut.at(1)?.text()).toBe('C');
        expect(wrapper.get('.ct-shortcut-overview-item__title').text()).toBe('Clear cache');
    });

    it('splits key sequences into multiple kbd elements', () => {
        const wrapper = createWrapper({ props: { title: 'Open filters', content: 'O F' } });

        expect(wrapper.findAll('kbd').map((key) => key.text())).toEqual([
            'O',
            'F',
        ]);
    });

    it('shows Mac key symbols', () => {
        const wrapper = createWrapper({
            platform: 'MacIntel',
            props: { title: 'Save detail view', content: 'ALT-S CONTROL-S CMD-S Shift-?' },
        });

        expect(wrapper.findAll('kbd').map((key) => key.text())).toEqual([
            '⌥',
            'S',
            '⌃',
            'S',
            '⌘',
            'S',
            '⇧',
            '?',
        ]);
        expect(wrapper.findAll('kbd').at(0)?.attributes('aria-label')).toBe('Option');
        expect(wrapper.findAll('kbd').at(4)?.attributes('aria-label')).toBe('Command');
    });

    it('shows Windows key labels', () => {
        const wrapper = createWrapper({
            platform: 'Win32',
            props: { title: 'Save detail view', content: 'CONTROL-S CMD-S ALT-C' },
        });

        expect(wrapper.findAll('kbd').map((key) => key.text())).toEqual([
            'Ctrl',
            'S',
            '⊞',
            'S',
            'Alt',
            'C',
        ]);
    });

    it('shows Linux key labels', () => {
        const wrapper = createWrapper({
            props: { title: 'Save detail view', content: 'CONTROL-S CMD-S ALT-C' },
        });

        expect(wrapper.findAll('kbd').map((key) => key.text())).toEqual([
            'Ctrl',
            'S',
            'Super',
            'S',
            'Alt',
            'C',
        ]);
    });

    it('shows tab key labels with the tab symbol', () => {
        const wrapper = createWrapper({ props: { title: 'Move focus backward', content: 'Tab Shift-Tab Enter' } });

        expect(wrapper.findAll('kbd').map((key) => key.text())).toEqual([
            '⇥ Tab',
            'Shift',
            '⇥ Tab',
            'Enter',
        ]);
    });

    it('hides an item when the required privilege is unavailable', () => {
        const wrapper = createWrapper({
            props: { title: 'Clear cache', content: 'ALT-C', privilege: 'system.clear_cache' },
        });

        expect(wrapper.find('.ct-shortcut-overview-item').exists()).toBe(false);
    });

    it('shows an item when the required privilege is available', () => {
        const wrapper = createWrapper({
            props: { title: 'Clear cache', content: 'ALT-C', privilege: 'system.clear_cache' },
            privileges: ['system.clear_cache'],
        });

        expect(wrapper.find('.ct-shortcut-overview-item').exists()).toBe(true);
    });
});
