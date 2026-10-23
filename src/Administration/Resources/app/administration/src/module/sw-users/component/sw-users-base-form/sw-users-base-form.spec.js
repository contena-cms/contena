import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const user = {
    id: '1',
    email: null,
    boundSalesChannelId: null,
    vatIds: ['9f8f091c-db81-4ef3-862c-9c554a34cdc4'],
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-user-base-form', { sync: true }), {
        props: {
            user,
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-entity-single-select': true,
                'sw-text-field': true,
                'sw-email-field': true,
                'sw-datepicker': true,
                'sw-entity-tag-select': true,
                'sw-single-select': true,
            },
        },
    });
}

describe('module/sw-user/page/sw-user-base-form', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = {
            type: 'not',
            operator: 'or',
            queries: [
                {
                    field: 'id',
                    type: 'equals',
                    value: 'ed643807c9f84cc8b50132ea3ccb1c3b',
                },
            ],
        };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });

    it('should display the account type switcher', async () => {
        const wrapper = await createWrapper();
        const accountTypeSelect = wrapper.find('.sw-user-base-form__account-type-select');
        expect(accountTypeSelect.exists()).toBeTruthy();
    });

    it('should display the language field', async () => {
        const wrapper = await createWrapper();
        const languageSelect = wrapper.find('.sw-user-base-form__language-select');
        expect(languageSelect.exists()).toBeTruthy();
    });

    it('should filter the selectable languages by the selected sales channel', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            user: {
                ...user,
                channelId: 'channelId1',
            },
        });

        const criteria = wrapper.vm.languageCriteria;

        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'channels.id',
            value: 'channelId1',
        });
    });

    it('should not filter the selectable languages when no sales channel is selected', async () => {
        const wrapper = await createWrapper();

        const criteria = wrapper.vm.languageCriteria;

        expect(criteria.filters).toHaveLength(0);
    });
});
