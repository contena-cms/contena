/**
 * @sw-package inventory
 */
import BlogTypeApiService from 'src/app/service/blog-type.api.service';

describe('app/service/blog-type.api.service.js', () => {
    it('should return the blog types', async () => {
        const httpClientMock = {
            get: jest.fn(() =>
                Promise.resolve({
                    data: ['digital', 'physical'],
                }),
            ),
        };

        const loginServiceMock = {
            getToken: jest.fn(() => Promise.resolve('token')),
        };

        const service = new BlogTypeApiService(httpClientMock, loginServiceMock);

        const result = await service.fetchProductTypes();

        expect(httpClientMock.get).toHaveBeenCalledWith('/_action/blog/types', expect.any(Object));

        expect(result).toStrictEqual(['digital', 'physical']);
    });
});
