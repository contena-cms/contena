import ThemeService from '../core/service/api/theme.api.service';

const { Application } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Service().register('themeService', (container) => {
    const initContainer = Application.getContainer('init');
    return new ThemeService(initContainer.httpClient, container.loginService);
});
