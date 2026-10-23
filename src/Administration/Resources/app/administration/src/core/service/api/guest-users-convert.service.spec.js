import GuestCustomerConvertService from 'src/core/service/api/guest-user-convert.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

/**
 * @sw-package checkout
 */

function getGuestCustomerConvertService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Contena.Context.api);

    const guestCustomerConvertService = new GuestCustomerConvertService(client, loginService);

    return {
        guestCustomerConvertService,
        clientMock,
    };
}

describe('GuestCustomerConvertService', () => {
    it('is registered correctly', () => {
        const { guestCustomerConvertService } = getGuestCustomerConvertService();

        expect(guestCustomerConvertService).toBeInstanceOf(GuestCustomerConvertService);
    });

    it('calls convert with correct endpoint and payload', async () => {
        const { guestCustomerConvertService, clientMock } = getGuestCustomerConvertService();

        const userId = 'test-user-id';

        const payload = {
            password: 'test-password',
        };

        let requestSent = false;

        clientMock.onPost(`/_action/user-convert/${userId}`, payload).reply((config) => {
            requestSent = true;

            expect(config.url).toBe(`/_action/user-convert/${userId}`);
            expect(JSON.parse(config.data)).toEqual(payload);

            return [
                200,
                {
                    success: true,
                },
            ];
        });

        const response = await guestCustomerConvertService.convert(userId, payload);

        await flushPromises();

        expect(requestSent).toBeTruthy();
        expect(response.success).toBe(true);
    });

    it('calls convert with correct endpoint without payload', async () => {
        const { guestCustomerConvertService, clientMock } = getGuestCustomerConvertService();

        const userId = 'test-user-id';

        let requestSent = false;

        clientMock.onPost(`/_action/user-convert/${userId}`).reply((config) => {
            requestSent = true;

            expect(config.url).toBe(`/_action/user-convert/${userId}`);

            return [
                200,
                {
                    success: true,
                },
            ];
        });

        const response = await guestCustomerConvertService.convert(userId);

        await flushPromises();

        expect(requestSent).toBeTruthy();
        expect(response.success).toBe(true);
    });

    it('throws error on convert request failure', async () => {
        const { guestCustomerConvertService, clientMock } = getGuestCustomerConvertService();

        const userId = 'test-user-id';

        clientMock.onPost(`/_action/user-convert/${userId}`).reply(400, {
            errors: [
                {
                    detail: 'Conversion failed',
                },
            ],
        });

        await expect(guestCustomerConvertService.convert(userId, {})).rejects.toThrow();
    });
});
