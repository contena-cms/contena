import { mount, type VueWrapper } from '@vue/test-utils';
import component from './index';

const shortcutService = {
    isShortcutsDisabled: jest.fn(() => false),
    setShortcutsDisabled: jest.fn(),
};

type ShortcutSection = {
    title: string;
    content: string;
};

type ShortcutOverviewVm = {
    showShortcutOverviewModal: boolean;
    shortcutsDisabled: boolean;
    sections: {
        generalShortcuts: ShortcutSection[];
        navigation: ShortcutSection[];
    };
    onOpenShortcutOverviewModal: () => void;
    onToggleShortcutsDisabled: (disabled: boolean) => void;
};

function getVm(wrapper: VueWrapper): ShortcutOverviewVm {
    return wrapper.vm as unknown as ShortcutOverviewVm;
}

function createWrapper(platform = 'Linux x86_64'): VueWrapper {
    Object.defineProperty(window.navigator, 'platform', { value: platform, configurable: true });

    return mount(component, {
        global: {
            provide: { shortcutService },
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'ct-shortcut-overview-item': true,
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { props: ['title'], template: '<div><slot /><slot name="footer" /></div>' },
                'mt-switch': true,
                'mt-button': { template: '<button><slot /></button>' },
            },
        },
    });
}

describe('app/component/utils/ct-shortcut-overview', () => {
    let wrapper: VueWrapper | undefined;

    beforeEach(() => {
        shortcutService.isShortcutsDisabled.mockReset().mockReturnValue(false);
        shortcutService.setShortcutsDisabled.mockReset();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
    });

    it('shows platform-specific shortcuts and the latest general actions', async () => {
        wrapper = createWrapper('MacIntel');
        const vm = getVm(wrapper);
        vm.onOpenShortcutOverviewModal();
        await wrapper.vm.$nextTick();

        expect(vm.sections.generalShortcuts).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    title: 'ct-shortcut-overview.functionSpecialShortcutToggleNavigation',
                    content: 'ct-shortcut-overview.keyboardShortcutSpecialShortcutToggleNavigation',
                }),
                expect.objectContaining({
                    title: 'ct-shortcut-overview.functionSpecialShortcutCycleTheme',
                    content: 'ct-shortcut-overview.keyboardShortcutSpecialShortcutCycleTheme',
                }),
                expect.objectContaining({
                    content: 'ct-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewMac',
                }),
            ]),
        );
    });

    it('contains only Contena generic content navigation shortcuts', () => {
        wrapper = createWrapper();
        const navigationTitles = getVm(wrapper).sections.navigation.map(({ title }) => title);

        expect(navigationTitles).toEqual([
            'ct-shortcut-overview.functionGoToDashboard',
            'ct-shortcut-overview.functionGoToBlogs',
            'ct-shortcut-overview.functionGoToCategories',
            'ct-shortcut-overview.functionGoToMembers',
            'ct-shortcut-overview.functionGoToExperienceStudio',
            'ct-shortcut-overview.functionGoToMedia',
            'ct-shortcut-overview.functionGoToSettingsListing',
            'ct-shortcut-overview.functionGoToSnippets',
            'ct-shortcut-overview.functionGoToRuleBuilder',
            'ct-shortcut-overview.functionGoToPlugins',
        ]);
    });

    it('shows all four upstream sections with footer actions', async () => {
        wrapper = createWrapper();
        const vm = getVm(wrapper);
        vm.onOpenShortcutOverviewModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.ct-shortcut-overview__section')).toHaveLength(4);
        expect(wrapper.get('mt-switch-stub').attributes('label')).toBe('ct-shortcut-overview.disableShortcuts');

        await wrapper.get('button').trigger('click');

        expect(vm.showShortcutOverviewModal).toBe(false);
    });

    it('toggles keyboard shortcuts through the shortcut service', () => {
        wrapper = createWrapper();
        const vm = getVm(wrapper);

        expect(vm.shortcutsDisabled).toBe(false);
        expect(shortcutService.isShortcutsDisabled).toHaveBeenCalled();

        vm.onToggleShortcutsDisabled(true);

        expect(vm.shortcutsDisabled).toBe(true);
        expect(shortcutService.setShortcutsDisabled).toHaveBeenCalledWith(true);
    });

    it('initializes the disabled state from the shortcut service', () => {
        shortcutService.isShortcutsDisabled.mockReturnValue(true);
        wrapper = createWrapper();

        expect(getVm(wrapper).shortcutsDisabled).toBe(true);
    });
});
