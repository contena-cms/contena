import { mount } from '@vue/test-utils';

describe('components/ct-select-result-list', () => {
    let swSelectResultList;

    beforeEach(async () => {
        swSelectResultList = mount(await wrapTestComponent('ct-select-result-list', { sync: true }), {
            global: {
                stubs: {
                    'mt-floating-ui': {
                        template: '<div><slot /></div>',
                    },
                },
            },
        });
        await flushPromises();
    });

    it('emits the paginate event when the element is scrolled to the bottom completely', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 800,
            },
        };

        swSelectResultList.vm.onScroll(scrollEvent);

        expect(swSelectResultList.emitted('paginate')).toHaveLength(1);
    });

    it('emits the paginate event when the element is scrolled to the bottom with less than one pixel remaining', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 799.1,
            },
        };

        swSelectResultList.vm.onScroll(scrollEvent);

        expect(swSelectResultList.emitted('paginate')).toHaveLength(1);
    });

    it('does not emit the paginate event when the element is not scrolled to the bottom', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 799,
            },
        };

        swSelectResultList.vm.onScroll(scrollEvent);
        expect(swSelectResultList.emitted('paginate')).toBeUndefined();
    });
});
