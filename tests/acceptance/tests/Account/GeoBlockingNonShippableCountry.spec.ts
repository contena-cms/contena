import { getCountryCodeFromLocale, test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Users is able to to register an account and selects a non-shippable country for their billing address.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Frontend',
        ],
    },
    async ({
        FrontendAccountLogin,
        FrontendAccount,
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
    }) => {
        const user = { email: IdProvider.getIdPair().uuid + '@test.com' };
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.channel.id, nonShippableCountry.id);
        const shippableCountry = await TestDataService.getCountry(getCountryCodeFromLocale());
        const registrationData = {
            salutation: 'Mr.',
            firstName: 'Jeff',
            lastName: 'Goldblum',
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'contena',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: `${nonShippableCountry.name} (Delivery not possible)`,
            postalCode: '48624',
        };

        await test.step('User cannot select non-shippable country for shipping address during registration', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.countryInput);
            await FrontendAccountLogin.countryInput.selectOption({ label: registrationData.country });
            await ShopCustomer.expects(
                await FrontendAccountLogin.getShippingCountryLocatorByName(registrationData.country),
            ).toBeDisabled();
        });

        await test.step('User submits the registration form successfully with a shippable country', async () => {
            await ShopCustomer.presses(FrontendAccountLogin.salutationSelect);
            await FrontendAccountLogin.salutationSelect.selectOption(registrationData.salutation);
            await ShopCustomer.fillsIn(FrontendAccountLogin.firstNameInput, registrationData.firstName);
            await ShopCustomer.fillsIn(FrontendAccountLogin.lastNameInput, registrationData.lastName);
            await ShopCustomer.fillsIn(FrontendAccountLogin.registerEmailInput, user.email);
            await ShopCustomer.fillsIn(FrontendAccountLogin.registerPasswordInput, registrationData.password);
            await ShopCustomer.fillsIn(FrontendAccountLogin.streetAddressInput, registrationData.street);
            await ShopCustomer.fillsIn(FrontendAccountLogin.postalCodeInput, registrationData.postalCode);
            await ShopCustomer.fillsIn(FrontendAccountLogin.cityInput, registrationData.city);
            await ShopCustomer.presses(FrontendAccountLogin.countryInput);
            await FrontendAccountLogin.countryInput.selectOption({ label: shippableCountry.name });
            await ShopCustomer.presses(FrontendAccountLogin.differentShippingAddressCheckbox);
            await ShopCustomer.presses(FrontendAccountLogin.shippingAddressSalutationSelect);
            await FrontendAccountLogin.shippingAddressSalutationSelect.selectOption(registrationData.salutation);
            await ShopCustomer.fillsIn(FrontendAccountLogin.shippingAddressFirstNameInput, registrationData.firstName);
            await ShopCustomer.fillsIn(FrontendAccountLogin.shippingAddressLastNameInput, registrationData.lastName);
            await ShopCustomer.fillsIn(FrontendAccountLogin.shippingAddressStreetAddressInput, registrationData.street);
            await ShopCustomer.fillsIn(FrontendAccountLogin.shippingAddressPostalCodeInput, registrationData.postalCode);
            await ShopCustomer.fillsIn(FrontendAccountLogin.shippingAddressCityInput, registrationData.city);
            await ShopCustomer.presses(FrontendAccountLogin.shippingAddressCountryInput);
            await FrontendAccountLogin.shippingAddressCountryInput.selectOption({ label: shippableCountry.name });
            await ShopCustomer.presses(FrontendAccountLogin.registerButton);
            await ShopCustomer.expects(FrontendAccount.headline).toBeVisible();
            const userId = (await TestDataService.getCustomerByEmail(user.email)).id;
            TestDataService.addCreatedRecord('user', userId);
        });
    },
);

test(
    'Users is not able to set new shipping address with a non-shippable country.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Frontend',
        ],
    },
    async ({
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        FrontendAccountLogin,
        Login,
        Logout,
        AddNewAddress,
        FrontendAccount,
        FrontendAccountAddresses,
        Register,
        InstanceMeta,
    }) => {
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.channel.id, nonShippableCountry.id);

        const address = {
            firstName: 'New First Name',
            lastName: 'New Last Name',
            company: 'contena',
            department: 'Operations',
            street: 'Ebbinghof 10',
            zipcode: '48624',
            city: 'Schöppingen',
            country: nonShippableCountry.name,
        };

        const user = {
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'contena',
            country: `${nonShippableCountry.name} (Delivery not possible)`,
        };

        // FrontendAccount.cannotDeliverToCountryAlert still expects the old "can not" wording
        const cannotDeliverToCountryAlert = FrontendAccount.page.getByText(
            'We cannot deliver to the country that is stored in your delivery address.',
        );

        await test.step('User select non-shippable country during registration', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(user));
            await ShopCustomer.expects(cannotDeliverToCountryAlert).toBeVisible();
        });

        await test.step('User see cannot deliver warning after re-login', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.attemptsTo(Login(user));
            await ShopCustomer.expects(cannotDeliverToCountryAlert).toBeVisible();
        });

        await test.step('User add new address with non-shippable country and cannot set it as new shipping address', async () => {
            await ShopCustomer.goesTo(FrontendAccountAddresses.url());
            await ShopCustomer.attemptsTo(AddNewAddress(address));
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(
                address.firstName + ' ' + address.lastName,
            );
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(address.street);
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(address.city);
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(address.zipcode);
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(address.country);
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(address.company);
            await ShopCustomer.expects(FrontendAccountAddresses.availableAddresses).toContainText(address.department);
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7')) {
                await ShopCustomer.expects(FrontendAccountAddresses.useDefaultBillingAddressButton).toBeEnabled();
                await ShopCustomer.expects(FrontendAccountAddresses.useDefaultShippingAddressButton).toBeDisabled();
                await ShopCustomer.expects(FrontendAccountAddresses.deliveryNotPossibleAlert).toBeVisible();
            } else {
                await ShopCustomer.presses(FrontendAccountAddresses.addressDropdownButtons.last());
                await ShopCustomer.expects(FrontendAccountAddresses.availableAddressesUseAsBillingAddress).toBeEnabled();
                await ShopCustomer.expects(FrontendAccountAddresses.availableAddressesUseAsShippingAddress).toBeDisabled();
            }
        });
    },
);
