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
    return mount(await wrapTestComponent('sw-user-card', { sync: true }), {
        props: {
            user: {},
            title: '',
        },
        global: {
            provide: {
                contextStoreService: {},
            },
            stubs: {
                'sw-avatar': true,
                'sw-entity-single-select': true,
                'sw-text-field': true,
                'sw-page': true,
                'sw-language-switch': true,
                'sw-user-address-form': true,
                'sw-user-base-form': true,
                'sw-card-view': true,
                'sw-button-process': true,
                'sw-email-field': true,
                'sw-entity-tag-select': true,
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-single-select': true,
                'sw-user-imitate-user-modal': true,
                'sw-label': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
            },
        },
    });
}

describe('module/sw-user/page/sw-user-card', () => {
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
        await wrapper.setProps({
            editMode: true,
        });
        const accountTypeSelect = wrapper.find('.sw-user-card__account-type-select');
        expect(accountTypeSelect.exists()).toBeTruthy();
    });

    it('should vat fields when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            user: {
                ...user,
                accountType: 'business',
            },
        });
        expect(wrapper.find('[aria-label="sw-user.card.labelCompany"]').exists()).toBeTruthy();
        expect(wrapper.find('[aria-label="sw-user.card.labelVatId"]').exists()).toBeTruthy();
    });

    it('should hide vat fields when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            user: {
                ...user,
                accountType: 'private',
            },
        });

        expect(wrapper.find('[label="sw-user.card.labelVatId"]').exists()).toBeFalsy();
    });
});
