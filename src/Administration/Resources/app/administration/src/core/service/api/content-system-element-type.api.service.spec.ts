import MockAdapter from 'axios-mock-adapter';
import type { AxiosInstance } from 'axios';
import createHTTPClient from '../../factory/http.factory';
import createLoginService from '../login.service';
import ContentSystemElementTypeApiService from './content-system-element-type.api.service';

function createContentSystemElementTypeApiService() {
    const context = Contena.Context?.api || {};
    const client = createHTTPClient(context) as AxiosInstance;
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const contentSystemElementTypeApiService = new ContentSystemElementTypeApiService(client, loginService);

    return {
        contentSystemElementTypeApiService,
        clientMock,
    };
}

describe('core/service/api/content-system-element-type.api.service.ts', () => {
    it('should be registered correctly', () => {
        const { contentSystemElementTypeApiService } = createContentSystemElementTypeApiService();

        expect(contentSystemElementTypeApiService).toBeInstanceOf(ContentSystemElementTypeApiService);
        expect(contentSystemElementTypeApiService.name).toBe('contentSystemElementTypeService');
    });

    it('should return content system element types', async () => {
        const { contentSystemElementTypeApiService, clientMock } = createContentSystemElementTypeApiService();
        const types = [
            {
                name: 'CT:Text',
                label: 'Text',
                description: 'A text element',
                source: 'core',
                icon: null,
                category: 'text',
                copilot: {
                    summary: 'Renders text',
                    hints: [],
                },
                properties: {},
                slots: [],
            },
        ];

        clientMock.onGet('/_info/content-system-element-types.json').reply(200, { types });

        await expect(contentSystemElementTypeApiService.getTypes()).resolves.toEqual(types);
    });

    it.each([
        undefined,
        null,
        {},
        'invalid',
    ])('should return an empty array for an invalid types payload', async (types) => {
        const { contentSystemElementTypeApiService, clientMock } = createContentSystemElementTypeApiService();

        clientMock.onGet('/_info/content-system-element-types.json').reply(200, { types });

        await expect(contentSystemElementTypeApiService.getTypes()).resolves.toEqual([]);
    });
});
