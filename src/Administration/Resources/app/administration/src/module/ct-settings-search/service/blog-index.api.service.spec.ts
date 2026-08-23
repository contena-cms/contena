import BlogIndexApiService from './blog-index.api.service';

describe('src/module/ct-settings-search/service/blog-index.api.service', () => {
    it('starts the Blog search index iteration', async () => {
        const response = { data: { finish: true, offset: 10 } };
        const client = { post: jest.fn(() => Promise.resolve(response)) };
        const service = new BlogIndexApiService(client as never, { getToken: () => 'token' } as never);

        await expect(service.index(10)).resolves.toBe(response);
        expect(client.post).toHaveBeenCalledWith('/_action/indexing/blog.indexer', { offset: 10 }, expect.any(Object));
        expect(service.name).toBe('blogIndexService');
    });
});
