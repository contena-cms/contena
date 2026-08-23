import MockAdapter from 'axios-mock-adapter';
import type { AxiosInstance } from 'axios';
import createHTTPClient from '../../factory/http.factory';
import createLoginService from '../login.service';
import ContentSystemStyleOptionApiService from './content-system-style-option.api.service';

function createContentSystemStyleOptionApiService() {
    const context = Contena.Context?.api || {};
    const client = createHTTPClient(context) as AxiosInstance;
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const contentSystemStyleOptionApiService = new ContentSystemStyleOptionApiService(client, loginService);

    return {
        contentSystemStyleOptionApiService,
        clientMock,
    };
}

describe('core/service/api/content-system-style-option.api.service.ts', () => {
    it('should be registered correctly', () => {
        const { contentSystemStyleOptionApiService } = createContentSystemStyleOptionApiService();

        expect(contentSystemStyleOptionApiService).toBeInstanceOf(ContentSystemStyleOptionApiService);
        expect(contentSystemStyleOptionApiService.name).toBe('contentSystemStyleOptionService');
    });

    it('should return content system style options', async () => {
        const { contentSystemStyleOptionApiService, clientMock } = createContentSystemStyleOptionApiService();
        const styleOptions = {
            display: {
                type: 'string',
                enum: [
                    'block',
                    'flex',
                ],
                range: null,
                maxLength: null,
                default: 'block',
                breakpointAware: true,
                adminUI: null,
            },
        };

        clientMock.onGet('/_info/content-system-style-options.json').reply(200, { styleOptions });

        await expect(contentSystemStyleOptionApiService.getStyleOptions()).resolves.toEqual(styleOptions);
    });

    it.each([
        undefined,
        null,
        [],
        'invalid',
    ])('should return an empty object for an invalid style options payload', async (styleOptions) => {
        const { contentSystemStyleOptionApiService, clientMock } = createContentSystemStyleOptionApiService();

        clientMock.onGet('/_info/content-system-style-options.json').reply(200, { styleOptions });

        await expect(contentSystemStyleOptionApiService.getStyleOptions()).resolves.toEqual({});
    });
});
