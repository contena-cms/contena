import { test, Address } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Make address default for billing.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Frontend',
        ],
        annotation: { type: 'story', description: 'As a shop user I want to set a default billing address' },
    },
    async ({ InstanceMeta, ShopCustomer, FrontendAccountAddresses, TestDataService, Login }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
        const user = await TestDataService.createCustomer();
        const address = await TestDataService.createCustomerAddress(user);
        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountAddresses.url());
        const addressContainer = await FrontendAccountAddresses.getAvailableAddress(address);
        await ShopCustomer.presses(addressContainer.addressActions);
        await ShopCustomer.presses(addressContainer.useAsDefaultBillingButton);
        await ShopCustomer.expects(addressContainer.isDefaultBillingAddress).toBeVisible();

        const defaultBillingAddress = await FrontendAccountAddresses.getDefaultBillingAddress(address);
        await ShopCustomer.expects(defaultBillingAddress).toBeVisible();
    },
);

test(
    'Make address default for shipping.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Frontend',
        ],
        annotation: { type: 'story', description: 'As a shop user I want to set a default shipping address' },
    },
    async ({ InstanceMeta, ShopCustomer, FrontendAccountAddresses, TestDataService, Login }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
        const user = await TestDataService.createCustomer();
        const address = await TestDataService.createCustomerAddress(user);
        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountAddresses.url());
        const addressContainer = await FrontendAccountAddresses.getAvailableAddress(address);
        await ShopCustomer.presses(addressContainer.addressActions);
        await ShopCustomer.presses(addressContainer.useAsDefaultShippingButton);
        await ShopCustomer.expects(addressContainer.isDefaultShippingAddress).toBeVisible();

        const defaultShippingAddress = await FrontendAccountAddresses.getDefaultShippingAddress(address);
        await ShopCustomer.expects(defaultShippingAddress).toBeVisible();
    },
);

test(
    'Edit an existing address.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Frontend',
        ],
        annotation: { type: 'story', description: 'As a shop user I want to edit an existing address' },
    },
    async ({
        InstanceMeta,
        ShopCustomer,
        FrontendAccountAddresses,
        FrontendAccountAddressDetails,
        TestDataService,
        Login,
    }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
        const user = await TestDataService.createCustomer();
        const address = await TestDataService.createCustomerAddress(user);
        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountAddresses.url());
        const addressContainer = await FrontendAccountAddresses.getAvailableAddress(address);
        await ShopCustomer.presses(addressContainer.addressActions);
        await ShopCustomer.presses(addressContainer.editAddressButton);

        await ShopCustomer.expects(FrontendAccountAddressDetails.firstNameInput).toHaveValue(address.firstName);
        await ShopCustomer.expects(FrontendAccountAddressDetails.lastNameInput).toHaveValue(address.lastName);
        await ShopCustomer.expects(FrontendAccountAddressDetails.streetInput).toHaveValue(address.street);
        await ShopCustomer.expects(FrontendAccountAddressDetails.zipcodeInput).toHaveValue(address.zipcode);
        await ShopCustomer.expects(FrontendAccountAddressDetails.cityInput).toHaveValue(address.city);

        const newAddress: Partial<Address> = {
            firstName: 'Egon',
            lastName: 'Spengler',
            street: 'Ghostbusters Ave 10',
            zipcode: '54321',
            city: 'Manhattan',
        };
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.firstNameInput, newAddress.firstName);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.lastNameInput, newAddress.lastName);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.streetInput, newAddress.street);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.zipcodeInput, newAddress.zipcode);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.cityInput, newAddress.city);
        await ShopCustomer.presses(FrontendAccountAddressDetails.saveAddressButton);

        const addressContainerEdited = await FrontendAccountAddresses.getAvailableAddress(newAddress);
        await ShopCustomer.expects(addressContainerEdited.address).toBeVisible();
    },
);

test(
    'Create a new address.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Frontend',
        ],
        annotation: { type: 'story', description: 'As a shop user I want to create a new address' },
    },
    async ({
        InstanceMeta,
        ShopCustomer,
        FrontendAccountAddresses,
        FrontendAccountAddressDetails,
        TestDataService,
        Login,
    }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
        const user = await TestDataService.createCustomer();
        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountAddresses.url());
        await ShopCustomer.presses(FrontendAccountAddresses.addNewAddressButton);
        const addressId = TestDataService.IdProvider.getIdPair();
        const newAddress: Partial<Address> = {
            id: addressId.uuid,
            firstName: 'Egon',
            lastName: 'Spengler',
            street: 'Ghostbusters Ave 10',
            zipcode: '54321',
            city: 'Manhattan',
        };
        TestDataService.addCreatedRecord('user_address', newAddress.id);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.firstNameInput, newAddress.firstName);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.lastNameInput, newAddress.lastName);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.streetInput, newAddress.street);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.zipcodeInput, newAddress.zipcode);
        await ShopCustomer.fillsIn(FrontendAccountAddressDetails.cityInput, newAddress.city);
        await ShopCustomer.presses(FrontendAccountAddressDetails.saveAddressButton);

        const addressContainer = await FrontendAccountAddresses.getAvailableAddress(newAddress);
        await ShopCustomer.expects(addressContainer.address).toBeVisible();
    },
);
