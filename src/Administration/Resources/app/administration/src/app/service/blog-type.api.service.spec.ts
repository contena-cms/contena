import BlogTypeApiService from 'src/app/service/blog-type.api.service';

describe('app/service/blog-type.api.service.ts', () => {
    it('should return the blog types', async () => {
        const httpClientMock = {
            get: jest.fn(() =>
                Promise.resolve({
                    data: [
                        'post',
                        'media',
                    ],
                }),
            ),
        };

        const loginServiceMock = {
            getToken: jest.fn(() => Promise.resolve('token')),
        };

        const service = new BlogTypeApiService(httpClientMock as never, loginServiceMock as never);

        const result = await service.fetchBlogTypes();

        expect(httpClientMock.get).toHaveBeenCalledWith('/_action/blog/types', expect.any(Object));

        expect(result).toStrictEqual([
            'post',
            'media',
        ]);
    });
});
