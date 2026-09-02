import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';
import component from './index';

const saveTemplateMock = jest.fn();
const saveConfigMock = jest.fn<Promise<void>, []>();

function createWrapper() {
    return mount(component, {
        global: {
            provide: {
                [routeLocationKey as symbol]: {
                    meta: {
                        $module: {
                            title: 'ct-settings-seo.general.mainMenuItemGeneral',
                        },
                    },
                },
            },
            stubs: {
                'ct-page': {
                    template:
                        '<div><slot name="smart-bar-header" /><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'ct-card-view': { template: '<div><slot /></div>' },
                'ct-skeleton': true,
                'ct-seo-url-template-card': {
                    name: 'CtSeoUrlTemplateCardStub',
                    methods: { onClickSave: saveTemplateMock },
                    template: '<div />',
                },
                'ct-system-config': {
                    name: 'CtSystemConfigStub',
                    props: { domain: String },
                    methods: { saveAll: saveConfigMock },
                    template: '<div />',
                },
                'mt-icon': true,
            },
        },
    });
}

describe('module/ct-settings-seo/page/ct-settings-seo', () => {
    beforeEach(() => {
        saveTemplateMock.mockReset();
        saveConfigMock.mockReset().mockResolvedValue();
    });

    it('loads the SEO configuration domain', () => {
        const wrapper = createWrapper();

        expect(wrapper.findComponent({ name: 'CtSystemConfigStub' }).props('domain')).toBe('core.seo');
    });

    it('saves URL templates and system configuration together', () => {
        const wrapper = createWrapper();

        (wrapper.vm as unknown as { onClickSave: () => void }).onClickSave();

        expect(saveTemplateMock).toHaveBeenCalledTimes(1);
        expect(saveConfigMock).toHaveBeenCalledTimes(1);
    });
});
