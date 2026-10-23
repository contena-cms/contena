import AdminUserActivityApiService from 'src/core/service/api/admin_user-activity.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createUserActivityApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Contena.Context.api);
    const admin_userActivityApiService = new AdminUserActivityApiService(client, loginService);
    return { admin_userActivityApiService, clientMock };
}

/**
 * @sw-package fundamentals@framework
 */
describe('admin_userActivityApiService', () => {
    it('is registered correctly', async () => {
        const { admin_userActivityApiService } = createUserActivityApiService();

        expect(admin_userActivityApiService).toBeInstanceOf(AdminUserActivityApiService);
    });

    it('increment frequently used correctly', async () => {
        const { admin_userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onPost('/_action/increment/admin_user_activity').reply(200, {
            success: true,
        });

        const data = {
            key: 'blog@sw.blog.index',
            cluster: 'id',
        };

        const trackActivity = await admin_userActivityApiService.increment(data);

        expect(trackActivity).toEqual({
            success: true,
        });
    });

    it('get frequently used correctly', async () => {
        const { admin_userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onGet('/_action/increment/admin_user_activity').reply(200, {
            data: [
                {
                    count: '3',
                    key: 'dashboard@sw.dashboard.index',
                },
                {
                    count: '2',
                    key: 'blog@sw.blog.index',
                },
            ],
        });

        const recentlySearch = await admin_userActivityApiService.getIncrement({
            cluster: 'id',
        });

        expect(recentlySearch).toEqual({
            data: [
                {
                    count: '3',
                    key: 'dashboard@sw.dashboard.index',
                },
                {
                    count: '2',
                    key: 'blog@sw.blog.index',
                },
            ],
        });
    });

    it('should make a DELETE request to the correct endpoint and handle success', async () => {
        const { admin_userActivityApiService, clientMock } = createUserActivityApiService();
        const paramsToDelete = {
            keys: ['key1@example', 'key2@example'],
            cluster: 'testUserId',
        };

        clientMock.onDelete('/_action/delete-increment/admin_user_activity', { params: paramsToDelete }).reply(204);

        const response = await admin_userActivityApiService.deleteActivityKeys(paramsToDelete);

        expect(response.status).toBe(204);
    });

    it('should handle API errors during deletion', async () => {
        const { admin_userActivityApiService, clientMock } = createUserActivityApiService();
        const paramsToDelete = {
            keys: ['key1@example'],
            cluster: 'testUserId',
        };
        const errorMessage = { message: 'Deletion failed' };

        clientMock.onDelete('/_action/delete-increment/admin_user_activity', { params: paramsToDelete }).reply(500, errorMessage);

        try {
            await admin_userActivityApiService.deleteActivityKeys(paramsToDelete);
        } catch (errorResponse) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(errorResponse.response.data).toEqual(errorMessage);
            // eslint-disable-next-line jest/no-conditional-expect
            expect(errorResponse.response.status).toBe(500);
        }
    });
});
