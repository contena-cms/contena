# Axios 1 HTTP Client Guide

## Overview

The Contena Administration uses Axios 1 for all HTTP requests. The former Axios 0 client, dual-client dispatcher, `useAxiosV1` request option, and version feature flag have been removed.

Use the injected `httpClient`, a DAL repository, or an API service instead of creating a separate Axios instance. This preserves authentication, response handling, and Administration extension behavior.

## Direct HTTP Client Usage

```javascript
export default {
    inject: ['httpClient'],

    methods: {
        async loadData() {
            const response = await this.httpClient.get('/api/example');

            return response.data;
        },
    },
};
```

## API Services

API services extend `ApiService` and use `this.httpClient`:

```javascript
class ExampleApiService extends ApiService {
    getExample() {
        return this.httpClient.get('/api/example', {
            headers: this.getBasicHeaders(),
        });
    }
}
```

## Request Cancellation

Axios 1 uses the standard `AbortController` API:

```javascript
const controller = new AbortController();

const request = httpClient.get('/api/example', {
    signal: controller.signal,
});

controller.abort();
```

Cancellation errors use the Axios `CanceledError` / `ERR_CANCELED` behavior. Existing code can use `httpClient.isCancel(error)` when it needs to distinguish cancellation from request failures.

## Testing

Use `axios-mock-adapter` with the client returned by the HTTP factory:

```javascript
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';

const httpClient = createHTTPClient();
const mock = new MockAdapter(httpClient);

mock.onGet('/api/example').reply(200, { data: 'test' });
```

## Additional Resources

- [Axios 1 migration guide](https://github.com/axios/axios/blob/v1.x/MIGRATION_GUIDE.md)
- [AbortController documentation](https://developer.mozilla.org/en-US/docs/Web/API/AbortController)
