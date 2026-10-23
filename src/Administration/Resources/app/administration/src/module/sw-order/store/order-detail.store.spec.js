/**
 * @sw-package checkout
 */

describe('src/module/sw-order/state/order-detail.store', () => {
    const state = Contena.Store.get('swOrderDetail');

    beforeEach(() => {
        state.$reset();
        state.resetCustomer();
    });

    function mockCustomerRepository(get) {
        return jest.spyOn(Contena.Service('repositoryFactory'), 'create').mockReturnValue({ get });
    }

    it('should be able to setOrder', () => {
        const newOrder = { id: 1, name: 'Test Order' };

        Contena.Store.get('swOrderDetail').order = newOrder;

        expect(state.order).toEqual(newOrder);
    });

    it('should be able to setLoading', () => {
        Contena.Store.get('swOrderDetail').setLoading(['order', true]);

        expect(state.loading.order).toBe(true);
        expect(Contena.Store.get('swOrderDetail').isLoading).toBe(true);
    });

    it('should be able to setSavedSuccessful', () => {
        Contena.Store.get('swOrderDetail').savedSuccessful = true;

        expect(state.savedSuccessful).toBe(true);
    });

    it('should be able to setVersionContext', () => {
        const versionContext = { versionId: 1, versionDate: '2021-01-01' };

        Contena.Store.get('swOrderDetail').versionContext = versionContext;

        expect(state.versionContext).toEqual(versionContext);
    });

    it('should be able to setEditing', () => {
        Contena.Store.get('swOrderDetail').editing = true;

        expect(state.editing).toBe(true);
        expect(Contena.Store.get('swOrderDetail').isEditing).toBe(true);
    });

    it('should clear order address ids when setOrderAddressIds gets null', () => {
        Contena.Store.get('swOrderDetail').setOrderAddressIds({
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        });

        Contena.Store.get('swOrderDetail').setOrderAddressIds(null);

        expect(state.orderAddressIds).toEqual([]);
    });

    it('should set order address ids when provided valid address info', () => {
        const addressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        Contena.Store.get('swOrderDetail').setOrderAddressIds(addressIdInfo);

        expect(state.orderAddressIds).toEqual([addressIdInfo]);
    });

    it('should not set order address ids when orderAddressId equals userAddressId', () => {
        const addressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d92db32071d689120d3dcf352197',
            type: 'billing',
        };

        Contena.Store.get('swOrderDetail').setOrderAddressIds(addressIdInfo);

        expect(state.orderAddressIds).toEqual([]);
    });

    it('should update userAddressId when orderAddressId and type match', () => {
        const initialAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        const updatedAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d926bb427e18aa3ceb00e23d090c',
            type: 'billing',
        };

        Contena.Store.get('swOrderDetail').setOrderAddressIds(initialAddressIdInfo);
        Contena.Store.get('swOrderDetail').setOrderAddressIds(updatedAddressIdInfo);

        expect(state.orderAddressIds).toEqual([updatedAddressIdInfo]);
    });

    it('should remove order address id when orderAddressId equals userAddressId and type match', () => {
        const initialAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d9275a6a72ae8b536849a4a02d85',
            type: 'billing',
        };

        const removalAddressIdInfo = {
            orderAddressId: '0190d92db32071d689120d3dcf352197',
            userAddressId: '0190d92db32071d689120d3dcf352197',
            type: 'billing',
        };

        Contena.Store.get('swOrderDetail').setOrderAddressIds(initialAddressIdInfo);
        expect(state.orderAddressIds).toEqual([initialAddressIdInfo]);

        Contena.Store.get('swOrderDetail').setOrderAddressIds(removalAddressIdInfo);
        expect(state.orderAddressIds).toEqual([]);
    });

    describe('loadCustomer', () => {
        const userId = '0190d9275a6a72ae8b536849a4a02d85';

        it('should load the user including the addresses of the address selections', async () => {
            const user = { id: userId };
            const get = jest.fn(() => Promise.resolve(user));
            mockCustomerRepository(get);

            await expect(state.loadCustomer(userId)).resolves.toBe(user);

            expect(state.user).toEqual(user);
            expect(get).toHaveBeenCalledWith(userId, Contena.Context.api, expect.any(Contena.Data.Criteria));

            const { associations } = get.mock.calls[0][2].parse();
            expect(Object.keys(associations)).toEqual(['addresses']);
            expect(Object.keys(associations.addresses.associations)).toEqual(['country']);
        });

        it('should share one user between concurrent callers instead of requesting it per address selection', async () => {
            const user = { id: userId };
            const get = jest.fn(() => Promise.resolve(user));
            mockCustomerRepository(get);

            const [
                billing,
                shipping,
            ] = await Promise.all([
                state.loadCustomer(userId),
                state.loadCustomer(userId),
            ]);

            expect(get).toHaveBeenCalledTimes(1);
            expect(billing).toBe(shipping);
        });

        it('should not request an already loaded user again', async () => {
            const get = jest.fn(() => Promise.resolve({ id: userId }));
            mockCustomerRepository(get);

            await state.loadCustomer(userId);
            await state.loadCustomer(userId);

            expect(get).toHaveBeenCalledTimes(1);
        });

        it('should request the user again when reloading it after an address was saved', async () => {
            const initial = { id: userId, addresses: [] };
            const reloaded = { id: userId, addresses: [{ id: 'newCustomerAddressId' }] };
            const get = jest.fn().mockResolvedValueOnce(initial).mockResolvedValueOnce(reloaded);
            mockCustomerRepository(get);

            await state.loadCustomer(userId);
            await state.loadCustomer(userId, true);

            expect(get).toHaveBeenCalledTimes(2);
            expect(state.user).toEqual(reloaded);
        });

        it('should load another user when the order belongs to a different one', async () => {
            const otherCustomerId = '0190d926bb427e18aa3ceb00e23d090c';
            const get = jest.fn().mockResolvedValueOnce({ id: userId }).mockResolvedValueOnce({ id: otherCustomerId });
            mockCustomerRepository(get);

            await state.loadCustomer(userId);
            await state.loadCustomer(otherCustomerId);

            expect(get).toHaveBeenCalledTimes(2);
            expect(state.user).toEqual({ id: otherCustomerId });
        });

        it('should load the user again after it was reset when leaving the order', async () => {
            const get = jest.fn(() => Promise.resolve({ id: userId }));
            mockCustomerRepository(get);

            await state.loadCustomer(userId);

            state.resetCustomer();
            expect(state.user).toBeNull();

            await state.loadCustomer(userId);

            expect(get).toHaveBeenCalledTimes(2);
        });
    });
});
