import MemberGroupRegistrationApiService from './member-group-registration.api.service';

describe('member-group-registration.api.service', () => {
    it.each([
        [
            'accept',
            '/_action/member-group-registration/accept',
        ],
        [
            'decline',
            '/_action/member-group-registration/decline',
        ],
    ] as const)('posts the member id for %s', async (method, endpoint) => {
        const post = jest.fn().mockResolvedValue({ data: {} });
        const service = new MemberGroupRegistrationApiService({ post } as never, { getToken: () => 'token' } as never);

        await service[method]('member-id');

        expect(post).toHaveBeenCalledWith(
            endpoint,
            { memberId: 'member-id' },
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            { headers: expect.objectContaining({ Authorization: 'Bearer token' }) },
        );
    });
});
