describe('src/init/api-service.init.js', () => {
    it('registers themeService with init container http client', () => {
        const registerSpy = jest.spyOn(Contena.Application, 'addServiceProvider');
        const getContainerSpy = jest.spyOn(Contena.Application, 'getContainer');

        const initContainer = {
            httpClient: {
                get: jest.fn(),
                post: jest.fn(),
                patch: jest.fn(),
            },
        };
        getContainerSpy.mockReturnValue(initContainer);

        jest.resetModules();

        const ThemeService = require('../core/service/api/theme.api.service').default;
        require('./api-service.init');

        const serviceRegistration = registerSpy.mock.calls.find(([serviceName]) => serviceName === 'themeService');

        expect(serviceRegistration).toBeDefined();

        const factory = serviceRegistration[1];
        const service = factory({ loginService: {} });

        expect(service).toBeInstanceOf(ThemeService);
        expect(service.httpClient).toBe(initContainer.httpClient);
    });
});
