import { parseApiRejection } from './login-error';

describe('module/ct-login/service/login-error', () => {
    it('normalizes an array JSON:API error', () => {
        expect(
            parseApiRejection({
                response: {
                    data: {
                        errors: [
                            {
                                status: '429',
                                code: 'FRAMEWORK__RATE_LIMIT_EXCEEDED',
                                meta: {
                                    parameters: {
                                        seconds: 30,
                                    },
                                },
                            },
                        ],
                    },
                },
            }),
        ).toEqual({
            status: 429,
            code: 'FRAMEWORK__RATE_LIMIT_EXCEEDED',
            retryAfterSeconds: 30,
        });
    });

    it('normalizes a single JSON:API error object', () => {
        expect(
            parseApiRejection({
                response: {
                    data: {
                        errors: {
                            status: '401',
                            code: 'FRAMEWORK__INVALID_CREDENTIALS',
                        },
                    },
                },
            }),
        ).toEqual({
            status: 401,
            code: 'FRAMEWORK__INVALID_CREDENTIALS',
            retryAfterSeconds: undefined,
        });
    });

    it.each([
        undefined,
        null,
        new Error('Network error'),
        { response: {} },
    ])('returns an empty result for a non-API rejection', (error) => {
        expect(parseApiRejection(error)).toEqual({});
    });
});
