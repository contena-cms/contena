import ErrorResolverSystemConfig from 'src/core/data/error-resolver.system-config.data';
import ContenaError from 'src/core/data/ContenaError';
import mock from './mocks/error-resolver.system-config.mock.json';

const errorResolverSystemConfig = new ErrorResolverSystemConfig();

describe('src/core/data/error-resolver.system-config.data.ts', () => {
    beforeEach(() => {
        Contena.Store.get('error').resetApiErrors();
    });

    it('should handleWriteErrors throw error', () => {
        const fn = () => errorResolverSystemConfig.handleWriteErrors(null);
        expect(fn).toThrow(Error);
    });

    it('should handleWriteErrors has system error', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.errorWithSystemError);

        const countSystemError = Contena.Store.get('error').countSystemError();

        expect(countSystemError).toBe(1);
    });

    it('should handleWriteErrors has api error', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.apiErrors);

        const result = Contena.Store.get('error').getSystemConfigApiError(
            ErrorResolverSystemConfig.ENTITY_NAME,
            null,
            'dummy.key',
        );

        expect(result).toBeInstanceOf(ContenaError);
    });

    it('should preserve the system config field name in the api error path', () => {
        errorResolverSystemConfig.handleWriteErrors([
            {
                code: 'scopedCode',
                status: '400',
                detail: 'This value should not be blank.',
                meta: { parameters: {} },
                source: { pointer: '/null/ConfigRenderer.config.textField' },
            },
        ]);

        const result = Contena.Store.get('error').getSystemConfigApiError(
            ErrorResolverSystemConfig.ENTITY_NAME,
            null,
            'ConfigRenderer.config.textField',
        );

        expect(result).toBeInstanceOf(ContenaError);
    });

    it('should handleWriteErrors has api error with translations', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.apiErrorsWithTranslation);

        const result = Contena.Store.get('error').getSystemConfigApiError(
            ErrorResolverSystemConfig.ENTITY_NAME,
            null,
            'dummy.key',
        );

        expect(result).toEqual({});
    });

    it('should cleanWriteErrors need clean all api errors', () => {
        errorResolverSystemConfig.handleWriteErrors(mock.apiErrors);

        errorResolverSystemConfig.cleanWriteErrors();

        const result = Contena.Store.get('error').getAllApiErrors();

        expect(result).toEqual([]);
    });
});
