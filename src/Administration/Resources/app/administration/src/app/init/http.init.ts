import type { AxiosInstance } from 'axios';

const HttpClient = Contena.Classes._private.HttpFactory;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeHttpClient(): AxiosInstance {
    return HttpClient(Contena.Context.api) as unknown as AxiosInstance;
}
