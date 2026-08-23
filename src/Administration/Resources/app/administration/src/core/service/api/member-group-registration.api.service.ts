import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from '../api.service';

class MemberGroupRegistrationApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'member-group-registration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'memberGroupRegistrationService';
    }

    accept(memberId: string): Promise<void> {
        return this.httpClient
            .post('/_action/member-group-registration/accept', { memberId }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    decline(memberId: string): Promise<void> {
        return this.httpClient
            .post('/_action/member-group-registration/decline', { memberId }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default MemberGroupRegistrationApiService;
