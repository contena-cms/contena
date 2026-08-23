import ExcludedSearchTermApiService from './excluded-search-term.api.service';

describe('src/core/service/api/excluded-search-term.api.service', () => {
    it('loads before the global Administration API is initialized', async () => {
        const runtimeGlobal = globalThis as unknown as Record<string, unknown>;
        const contena = runtimeGlobal.Contena;
        Reflect.deleteProperty(runtimeGlobal, 'Contena');

        try {
            await jest.isolateModulesAsync(async () => {
                await expect(import('./excluded-search-term.api.service')).resolves.toHaveProperty('default');
            });
        } finally {
            runtimeGlobal.Contena = contena;
        }
    });

    it('resets excluded Blog search terms with the current language', async () => {
        const client = { post: jest.fn(() => Promise.resolve({ data: { success: true } })) };
        const service = new ExcludedSearchTermApiService(client as never, { getToken: () => 'token' } as never);

        await expect(service.resetExcludedSearchTerm()).resolves.toEqual({ data: { success: true } });
        expect(client.post).toHaveBeenCalledWith(
            '/_admin/reset-excluded-search-term',
            {},
            expect.objectContaining({
                // Jest's asymmetric matcher is intentionally untyped.
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                headers: expect.objectContaining({ 'ct-language-id': Contena.Context.api.languageId }),
            }),
        );
        expect(service.name).toBe('excludedSearchTermService');
    });
});
