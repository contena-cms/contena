import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import type { SubContainer } from 'src/global.types';

/** @private */
export default class CaptchaService {
    public readonly name = 'captchaService';

    public constructor(
        private readonly httpClient: AxiosInstance,
        public readonly loginService: LoginService,
    ) {}

    public list(callback: (captchas: string[]) => void): void {
        const headers = this.getAuthHeaders();

        void this.httpClient.get<string[]>('/_action/captcha_list', { headers }).then((response) => callback(response.data));
    }

    public getAuthHeaders(): Record<string, string> {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        captchaService: CaptchaService;
    }
}
