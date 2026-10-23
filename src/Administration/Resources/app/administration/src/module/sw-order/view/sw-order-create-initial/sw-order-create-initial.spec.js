/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/user',
    status: 200,
    response: {
        data: [
            {
                id: '1234',
                attributes: {
                    id: '1234',
                },
                relationships: [],
            },
        ],
    },
});

async function createWrapper(userId = null) {
    return mount(await wrapTestComponent('sw-order-create-initial', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    query: {
                        userId: userId,
                    },
                },
            },
            stubs: {
                'sw-order-create-initial-modal': true,
                'mt-loader': true,
            },
        },
    });
}

describe('src/module/sw-order/view/sw-order-create-initial', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        Contena.Store.get('swOrder').$reset();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should not load a user if no userId query parameter has been passed', async () => {
        wrapper.vm.userRepository.get = jest.fn();
        wrapper.vm.createdComponent();
        await flushPromises();

        expect(wrapper.vm.userRepository.get).not.toHaveBeenCalled();
    });

    it('should not update the state if no user is found', async () => {
        wrapper = await createWrapper('9876');
        await flushPromises();

        const user = Contena.Store.get('swOrder').user;
        expect(user).toBeNull();
    });

    it('should update the state if a user is found', async () => {
        wrapper = await createWrapper('1234');
        await flushPromises();

        const user = Contena.Store.get('swOrder').user;
        expect(user).toEqual(
            expect.objectContaining({
                id: '1234',
            }),
        );
    });
});
