import { mount } from '@vue/test-utils';

describe('components/ct-select-result-list', () => {
    let ctSelectResultList;

    beforeEach(async () => {
        ctSelectResultList = mount(await wrapTestComponent('ct-select-result-list', { sync: true }), {
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

        ctSelectResultList.vm.onScroll(scrollEvent);

        expect(ctSelectResultList.emitted('paginate')).toHaveLength(1);
    });

    it('emits the paginate event when the element is scrolled to the bottom with less than one pixel remaining', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 799.1,
            },
        };

        ctSelectResultList.vm.onScroll(scrollEvent);

        expect(ctSelectResultList.emitted('paginate')).toHaveLength(1);
    });

    it('does not emit the paginate event when the element is not scrolled to the bottom', async () => {
        const scrollEvent = {
            target: {
                scrollHeight: 1000,
                clientHeight: 200,
                scrollTop: 799,
            },
        };

        ctSelectResultList.vm.onScroll(scrollEvent);
        expect(ctSelectResultList.emitted('paginate')).toBeUndefined();
    });
});
