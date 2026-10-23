/**
 * @sw-package framework
 */
import initPost from 'src/app/init-post/index';

describe('src/app/init-post/index.ts', () => {
    it('should export all post initializer', () => {
        expect(initPost).toEqual({
            language: expect.any(Function),
            admin_userInformation: expect.any(Function),
            worker: expect.any(Function),
            telemetry: expect.any(Function),
            blogAnalytics: expect.any(Function),
        });
    });
});
