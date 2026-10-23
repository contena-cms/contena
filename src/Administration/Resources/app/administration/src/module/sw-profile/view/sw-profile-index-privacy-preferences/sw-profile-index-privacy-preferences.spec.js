import { mount } from '@vue/test-utils';
import SwSettingsUsageDataProfileConsent from 'src/module/sw-settings-usage-data/component/sw-settings-usage-data-profile-consent';
import SwProfileIndexPrivacyPreferences from './index';

describe('/module/sw-profile/view/sw-profile-index-privacy-preferences', () => {
    it('shows admin_user data consent component', async () => {
        const wrapper = await mount(SwProfileIndexPrivacyPreferences, {
            global: {
                stubs: {
                    SwSettingsUsageDataProfileConsent,
                },
            },
        });

        expect(wrapper.findComponent(SwSettingsUsageDataProfileConsent).exists()).toBe(true);
    });
});
