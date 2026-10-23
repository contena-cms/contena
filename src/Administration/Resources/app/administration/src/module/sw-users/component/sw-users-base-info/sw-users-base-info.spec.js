import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/order',
    status: 200,
    response: {
        data: [
            {
                id: '1',
            },
        ],
    },
});

responses.addResponse({
    method: 'Post',
    url: '/search/language',
    status: 200,
    response: {
        data: [
            {
                id: '1',
            },
        ],
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-user-base-info', { sync: true }), {
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-description-list': await wrapTestComponent('sw-description-list', { sync: true }),
                'sw-loader': true,
                'sw-entity-single-select': true,
                'sw-checkbox-field': true,
                'sw-help-text': true,
                'sw-datepicker': true,
                'sw-text-field': true,
                'sw-time-ago': await wrapTestComponent('sw-time-ago', { sync: true }),
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () =>
                            Promise.resolve({
                                total: 2,
                            }),
                        get: () => Promise.resolve(),
                    }),
                },
            },
        },
        props: {
            user: {
                birthday: '1992-12-22T00:00:00.000+00:00',
                lastLogin: '2021-10-14T11:23:44.195+00:00',
                doubleOptInRegistration: false,
                doubleOptInConfirmDate: null,
                group: {
                    translated: {
                        name: 'Group test',
                    },
                },
                orderTotalAmount: 29.68,
            },
            userEditMode: false,
            isLoading: false,
        },
    });
}

describe('module/sw-user/page/sw-user-base-info', () => {
    let wrapper;

    beforeEach(async () => {
        Contena.Context.app.systemCurrencyISOCode = 'GBP';
        wrapper = await createWrapper();
    });

    it('should display the birthday', async () => {
        expect(wrapper.find('.sw-user-base__label-birthday').text()).toBe('22/12/1992');
    });

    it('should display the empty birthday snippet placeholder', async () => {
        await wrapper.setProps({
            user: {
                ...wrapper.props().user,
                birthday: null,
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-user-base__label-birthday').text()).toBe('sw-user.baseInfo.emptyTextBirthday');
    });

    it('should display the last login date', async () => {
        expect(wrapper.find('.sw-user-base__label-last-login').text()).toBe('14/10/2021, 11:23');
    });

    it('should display the last login snippet placeholder', async () => {
        await wrapper.setProps({
            user: {
                ...wrapper.props().user,
                lastLogin: null,
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-user-base__label-last-login').text()).toBe('sw-user.baseInfo.emptyTextLogin');
    });

    it('should display turnover of admin_user', async () => {
        expect(wrapper.find('.sw-user-base__label-turnover').text()).toBe('£29.68');
    });

    it('should hide some information when displayed in edit mode', async () => {
        await wrapper.setProps({
            user: {
                ...wrapper.props().user,
                accountType: 'business',
                company: 'Contena',
                vatIds: ['12345'],
                userNumber: '123456789',
            },
            userEditMode: true,
        });

        await wrapper.vm.$nextTick();

        const leftColumn = wrapper.findAll('.sw-user-base-info-columns')[0];

        expect(
            leftColumn.findAll('.sw-description-list').filter((w) =>
                [
                    'sw-user.baseInfo.labelCompany',
                    'sw-user.baseInfo.labelVatId',
                    'sw-user.baseInfo.labelConfirmed',
                ].includes(w.find('dt').text()),
            ),
        ).toEqual([]);

        const rightColumn = wrapper.findAll('.sw-user-base-info-columns')[1];

        expect(
            rightColumn
                .findAll('.sw-description-list')
                .filter((w) =>
                    ['sw-user.baseInfo.labelCompany', 'sw-user.baseInfo.labelVatId'].includes(w.find('dt').text()),
                ),
        ).toEqual([]);
    });

    it('should display user information in no edit mode', async () => {
        await wrapper.setProps({
            user: {
                ...wrapper.props().user,
                accountType: 'business',
                company: 'Contena',
                vatIds: ['12345'],
                userNumber: '123456789',
            },
        });

        await wrapper.vm.$nextTick();

        const leftColumn = wrapper.findAll('.sw-user-base-info-columns')[0];

        // Company
        expect(leftColumn.findAll('.sw-description-list')[0].find('dt').text()).toBe('sw-user.baseInfo.labelCompany');
        expect(leftColumn.findAll('.sw-description-list')[0].find('dd').text()).toBe('Contena');

        // VAT
        expect(leftColumn.findAll('.sw-description-list')[1].find('dt').text()).toBe('sw-user.baseInfo.labelVatId');
        expect(leftColumn.findAll('.sw-description-list')[1].find('dd').text()).toBe('12345');

        // User group
        expect(leftColumn.findAll('.sw-description-list')[2].find('dt').text()).toBe(
            'sw-user.baseInfo.labelCustomerGroup',
        );
        expect(leftColumn.findAll('.sw-description-list')[2].find('dd').text()).toBe('Group test');

        // Affiliate code
        expect(leftColumn.findAll('.sw-description-list')[5].find('dt').text()).toBe(
            'sw-user.baseInfo.labelAffiliateCode',
        );
        expect(leftColumn.findAll('.sw-description-list')[5].find('dd').text()).toBe('-');

        // Campaign code
        expect(leftColumn.findAll('.sw-description-list')[6].find('dt').text()).toBe(
            'sw-user.baseInfo.labelCampaignCode',
        );
        expect(leftColumn.findAll('.sw-description-list')[6].find('dd').text()).toBe('-');

        // Email confirmation shouldn't be displayed
        expect(leftColumn.findAll('.sw-description-list').map((w) => w.find('dt').text())).not.toContain(
            'sw-user.baseInfo.labelConfirmed',
        );
        expect(leftColumn.findAll('.sw-description-list').map((w) => w.find('dd').text())).not.toContain(
            'sw-user.baseInfo.contentConfirmed',
        );

        const rightColumn = wrapper.findAll('.sw-user-base-info-columns')[1];

        // User number
        expect(rightColumn.findAll('.sw-description-list')[0].find('dt').text()).toBe(
            'sw-user.baseInfo.labelCustomerNumber',
        );
        expect(rightColumn.findAll('.sw-description-list')[0].find('dd').text()).toBe('123456789');
    });

    it('should display email confirmation status in no edit mode', async () => {
        await wrapper.setProps({
            user: {
                ...wrapper.props().user,
                doubleOptInRegistration: true,
                doubleOptInConfirmDate: null,
            },
        });

        await wrapper.vm.$nextTick();

        const leftColumn = wrapper.findAll('.sw-user-base-info-columns')[0];

        // Email confirmation status
        expect(leftColumn.findAll('.sw-description-list')[2].find('dt').text()).toBe('sw-user.baseInfo.labelConfirmed');
        expect(leftColumn.findAll('.sw-description-list')[2].find('dd').text()).toBe(
            'sw-user.baseInfo.contentConfirmed',
        );
    });

    it('should hide affiliate and campaign code value in edit mode', async () => {
        await wrapper.setProps({
            userEditMode: true,
        });

        await wrapper.vm.$nextTick();

        const leftColumn = wrapper.findAll('.sw-user-base-info-columns')[0];
        // Affiliate code
        expect(leftColumn.findAll('.sw-description-list')[3].find('dt').text()).toBe(
            'sw-user.baseInfo.labelAffiliateCode',
        );
        expect(leftColumn.findAll('.sw-description-list')[3].find('dd').text()).not.toBe('-');

        // Campaign code
        expect(leftColumn.findAll('.sw-description-list')[4].find('dt').text()).toBe(
            'sw-user.baseInfo.labelCampaignCode',
        );
        expect(leftColumn.findAll('.sw-description-list')[4].find('dd').text()).not.toBe('-');
    });
});
