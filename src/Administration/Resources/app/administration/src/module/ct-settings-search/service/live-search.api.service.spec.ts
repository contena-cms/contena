import LiveSearchApiService from './live-search.api.service';

describe('src/module/ct-settings-search/service/live-search.api.service', () => {
    it('searches Blog content through the Channel API proxy', async () => {
        const response = { data: { data: [] } };
        const client = { post: jest.fn(() => Promise.resolve(response)) };
        const service = new LiveSearchApiService(client as never, { getToken: () => 'token' } as never);

        await expect(
            service.search({ channelId: 'channel-id', search: 'article', order: 'relevance' }, 'context-token'),
        ).resolves.toBe(response);
        expect(client.post).toHaveBeenCalledWith(
            '_proxy/channel-api/channel-id/search',
            { channelId: 'channel-id', search: 'article', order: 'relevance' },
            expect.objectContaining({
                // Jest's asymmetric matcher is intentionally untyped.
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                headers: expect.objectContaining({ 'ct-context-token': 'context-token' }),
                params: {},
            }),
        );
        expect(service.name).toBe('liveSearchService');
    });
});
