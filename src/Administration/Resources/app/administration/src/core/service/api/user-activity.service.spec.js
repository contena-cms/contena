import UserActivityApiService from 'src/core/service/api/user-activity.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createUserActivityApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Contena.Context.api);
    const userActivityApiService = new UserActivityApiService(client, loginService);

    return { userActivityApiService, clientMock };
}

describe('userActivityApiService', () => {
    it('is registered correctly', async () => {
        const { userActivityApiService } = createUserActivityApiService();

        expect(userActivityApiService).toBeInstanceOf(UserActivityApiService);
    });

    it('increments frequently used modules correctly', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onPost('/_action/increment/user_activity').reply(200, {
            success: true,
        });

        const data = {
            key: 'product@ct.product.index',
            cluster: 'id',
        };

        const trackActivity = await userActivityApiService.increment(data);

        expect(trackActivity).toEqual({
            success: true,
        });
    });

    it('gets frequently used modules correctly', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onGet('/_action/increment/user_activity').reply(200, {
            data: [
                {
                    count: '3',
                    key: 'dashboard@ct.dashboard.index',
                },
                {
                    count: '2',
                    key: 'product@ct.product.index',
                },
            ],
        });

        const frequentlyUsed = await userActivityApiService.getIncrement({
            cluster: 'id',
        });

        expect(frequentlyUsed).toEqual({
            data: [
                {
                    count: '3',
                    key: 'dashboard@ct.dashboard.index',
                },
                {
                    count: '2',
                    key: 'product@ct.product.index',
                },
            ],
        });
    });

    it('deletes activity keys correctly', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();
        const paramsToDelete = {
            keys: [
                'key1@example',
                'key2@example',
            ],
            cluster: 'testUserId',
        };

        clientMock.onDelete('/_action/delete-increment/user_activity', { params: paramsToDelete }).reply(204);

        const response = await userActivityApiService.deleteActivityKeys(paramsToDelete);

        expect(response.status).toBe(204);
    });

    it('propagates errors when deleting activity keys fails', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();
        const paramsToDelete = {
            keys: ['key1@example'],
            cluster: 'testUserId',
        };
        const errorMessage = { message: 'Deletion failed' };

        clientMock.onDelete('/_action/delete-increment/user_activity', { params: paramsToDelete }).reply(500, errorMessage);

        await expect(userActivityApiService.deleteActivityKeys(paramsToDelete)).rejects.toMatchObject({
            response: {
                data: errorMessage,
                status: 500,
            },
        });
    });
});
