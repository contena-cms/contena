import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import CaptchaService from './captcha.service';

describe('src/module/ct-settings-basic-information/service/captcha.service', () => {
    const response = [
        'technical',
        'names',
    ];
    const token = 'fce2c5c0-518c-4f16-b893-4f0913c07efe';

    let captchaService: CaptchaService;

    beforeEach(() => {
        const httpClient = {
            get: jest.fn(() =>
                Promise.resolve({
                    data: response,
                }),
            ),
        } as unknown as AxiosInstance;

        const loginService = {
            getToken: jest.fn(() => token),
        } as unknown as LoginService;

        captchaService = new CaptchaService(httpClient, loginService);
    });

    it('should be initialized', () => {
        expect(captchaService).not.toBeNull();
        expect(captchaService).toBeInstanceOf(CaptchaService);
    });

    it('should return auth headers', () => {
        expect(captchaService.getAuthHeaders()).toMatchObject({
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        });
        expect(captchaService.loginService.getToken).toHaveBeenCalledTimes(1);
    });

    it('should provide the list callback with data', async () => {
        const callback = jest.fn();

        captchaService.list(callback);
        await Promise.resolve();

        expect(callback).toHaveBeenCalledWith(response);
    });
});
