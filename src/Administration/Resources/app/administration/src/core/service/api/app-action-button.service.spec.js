import AppActionButtonService from 'src/core/service/api/app-action-button.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import InvalidActionButtonParameterError from './errors/InvalidActionButtonParameterError';

function createService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    return { appActionButtonService: new AppActionButtonService(client, createLoginService(client, Contena.Context.api)), clientMock };
}

describe('appActionButtonService', () => {
    it('is registered correctly', () => {
        expect(createService().appActionButtonService).toBeInstanceOf(AppActionButtonService);
    });

    it('requires an entity and view', () => {
        const { appActionButtonService } = createService();
        expect(() => appActionButtonService.getActionButtonsPerView()).toThrow(new InvalidActionButtonParameterError('Parameter "entity" must have a valid value. Given: undefined'));
        expect(() => appActionButtonService.getActionButtonsPerView('blog')).toThrow(new InvalidActionButtonParameterError('Parameter "view" must have a valid value. Given: undefined'));
    });

    it('returns action button data and runs an action', async () => {
        const { appActionButtonService, clientMock } = createService();
        clientMock.onGet('app-system/action-button/blog/detail').reply(200, { actions: [{ name: 'App' }] });
        expect(await appActionButtonService.getActionButtonsPerView('blog', 'detail')).toEqual([{ name: 'App' }]);
        const id = Contena.Utils.createId();
        clientMock.onPost(`app-system/action-button/run/${id}`).reply(200, null);
        await appActionButtonService.runAction(id);
        expect(clientMock.history.post[0].url).toBe(`app-system/action-button/run/${id}`);
    });
});
