import ExtensionSdkService from 'src/core/service/api/extension-sdk.service';

describe('extensionSdkService', () => {
    it('calls the sign-uri route with the App source', async () => {
        const httpClientMock = { post: jest.fn(() => Promise.resolve({ data: 'signed-url' })) };
        const loginServiceMock = { getToken: jest.fn(() => Promise.resolve('token')) };
        const service = new ExtensionSdkService(httpClientMock, loginServiceMock);
        const result = await service.signIframeSrc('ContenaExampleApp', 'https://example.test/app.html');
        expect(httpClientMock.post).toHaveBeenCalledWith(
            '/_action/extension-sdk/sign-uri',
            { appName: 'ContenaExampleApp', uri: 'https://example.test/app.html' },
            expect.any(Object),
        );
        expect(result).toBe('signed-url');
    });
});
