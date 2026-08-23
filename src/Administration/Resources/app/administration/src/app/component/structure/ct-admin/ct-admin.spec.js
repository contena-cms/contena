import 'src/app/component/structure/ct-admin';
import { mount } from '@vue/test-utils';

async function createWrapper(isLoggedIn) {
    return mount(await wrapTestComponent('ct-admin', { sync: true }), {
        global: {
            stubs: {
                'ct-notifications': true,
                'ct-duplicated-media-v2': true,
                'ct-settings-cache-modal': true,
                'router-view': true,
                'ct-skip-link': true,
                'ct-media-modal-renderer': true,
                'ct-upload-status': true,
                'mt-snackbar': true,
                'mt-theme-provider': {
                    name: 'MtThemeProvider',
                    props: ['future'],
                    template: '<div><slot /></div>',
                },
            },
            provide: {
                cacheApiService: {},
                extensionStoreActionService: {},
                loginService: {
                    isLoggedIn: () => isLoggedIn,
                },
            },
        },
        attachTo: document.body,
    });
}

describe('src/app/component/structure/ct-admin/index.ts', () => {
    let wrapper;

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('opts cards into the responsive Meteor width behavior', async () => {
        wrapper = await createWrapper(false);

        expect(wrapper.findComponent({ name: 'MtThemeProvider' }).props('future')).toEqual({
            removeCardWidth: true,
        });
    });
});
