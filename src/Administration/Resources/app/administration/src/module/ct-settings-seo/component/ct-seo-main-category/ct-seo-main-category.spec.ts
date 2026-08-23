import { shallowMount, type VueWrapper } from '@vue/test-utils';
import '../ct-seo-url/store';
import component from './index';

const mtSelectStub = {
    name: 'MtSelectStub',
    props: [
        'label',
        'disabled',
        'modelValue',
    ],
    template: '<div class="mt-select-stub" />',
};

type MainCategoryVm = {
    mainCategoryForChannel: Record<string, unknown> | null;
    onMainCategorySelected: (categoryId: string | null) => void;
};

function createWrapper(props: Record<string, unknown> = {}): VueWrapper<MainCategoryVm> {
    return shallowMount(component, {
        props: {
            mainCategories: [],
            categories: [],
            ...props,
        },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-select': mtSelectStub,
            },
            provide: {
                repositoryFactory: {
                    create: jest.fn(() => ({ create: jest.fn() })),
                },
            },
        },
    }) as unknown as VueWrapper<MainCategoryVm>;
}

describe('module/ct-settings-seo/component/ct-seo-main-category', () => {
    it('hides the label when the parent overwrites it', () => {
        const wrapper = createWrapper({ overwriteLabel: true });

        expect(wrapper.findComponent(mtSelectStub).props('label')).toBeUndefined();
    });

    it('shows the default label', () => {
        const wrapper = createWrapper();

        expect(wrapper.findComponent(mtSelectStub).props('label')).toBe('ct-seo-url.labelMainCategory');
    });

    it('emits removal when the selected category is cleared', () => {
        const mainCategory = {
            channelId: 'channel-1',
            categoryId: 'category-1',
            category: { id: 'category-1', translated: { name: 'Category 1' } },
        };
        const wrapper = createWrapper({
            currentChannelId: 'channel-1',
            mainCategories: [mainCategory],
            categories: [mainCategory.category],
        });

        expect(wrapper.vm.mainCategoryForChannel).toEqual(mainCategory);
        wrapper.vm.onMainCategorySelected(null);

        expect(wrapper.emitted('main-category-remove')).toEqual([[mainCategory]]);
    });
});
