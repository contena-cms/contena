import ChannelApiService from './channel.api.service';

describe('src/core/service/api/channel.api.service', () => {
    it('generates a Channel access key through the upstream action endpoint', async () => {
        const response = { data: { accessKey: 'channel-key' } };
        const client = {
            get: jest.fn(() => Promise.resolve(response)),
        };
        const service = new ChannelApiService(client as never, { getToken: () => 'token' } as never);

        await expect(service.generateKey()).resolves.toEqual(response.data);
        expect(client.get).toHaveBeenCalledWith('/_action/access-key/channel', {
            params: {},
            // Jest's asymmetric matcher is intentionally untyped.
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            headers: expect.any(Object),
        });
        expect(service.name).toBe('channelService');
    });
});
