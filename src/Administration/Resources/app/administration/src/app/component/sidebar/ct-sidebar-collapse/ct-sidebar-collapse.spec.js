import { mount } from '@vue/test-utils';
import 'src/app/component/base/ct-collapse';
import 'src/app/component/sidebar/ct-sidebar-collapse';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-sidebar-collapse', { sync: true }), {
        global: {
            stubs: {
                'ct-collapse': true,
            },
            mocks: {
                $t: (snippetPath, count, values) => snippetPath + count + JSON.stringify(values),
            },
        },
    });
}

describe('src/app/component/sidebar/ct-sidebar-collapse', () => {
    describe('no props', () => {
        it('has a chevron pointing right', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.findComponent('.mt-icon').vm.name).toBe('regular-chevron-right-xs');
        });
    });

    describe('all directions', () => {
        [
            'up',
            'left',
            'right',
            'down',
        ].forEach((direction) => {
            it(`has a chevron pointing ${direction}`, async () => {
                const wrapper = await createWrapper();

                await wrapper.setProps({
                    expandChevronDirection: direction,
                });

                expect(wrapper.findComponent('.mt-icon').vm.name).toBe(`regular-chevron-${direction}-xs`);
            });
        });
    });
});
