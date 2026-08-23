import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { CONTENA_CORE_MODULES } from './index';
import flowDetail from './ct-flow/page/ct-flow-detail';
import flowIndex from './ct-flow/page/ct-flow-index';
import flowMailSendModal from './ct-flow/component/ct-flow-mail-send-modal';
import flowSequenceEditor from './ct-flow/component/ct-flow-sequence-editor';
import ruleConditionEditor from './ct-settings-rule/component/ct-rule-condition-editor';
import ruleDetail from './ct-settings-rule/page/ct-settings-rule-detail';
import ruleList from './ct-settings-rule/page/ct-settings-rule-list';
import listingDeleteModal from './ct-settings-listing/component/ct-settings-listing-delete-modal';
import listingOptionCriteriaGrid from './ct-settings-listing/component/ct-settings-listing-option-criteria-grid';
import listingOptionGeneralInfo from './ct-settings-listing/component/ct-settings-listing-option-general-info';
import listing from './ct-settings-listing/page/ct-settings-listing';
import listingOptionBase from './ct-settings-listing/page/ct-settings-listing-option-base';
import listingOptionCreate from './ct-settings-listing/page/ct-settings-listing-option-create';
import seoMainCategory from './ct-settings-seo/component/ct-seo-main-category';
import seoUrl from './ct-settings-seo/component/ct-seo-url';
import seoUrlTemplateCard from './ct-settings-seo/component/ct-seo-url-template-card';
import settingsSeo from './ct-settings-seo/page/ct-settings-seo';
import settingsBasicInformation from './ct-settings-basic-information/page/ct-settings-basic-information';
import settingsCaptchaSelect from './ct-settings-basic-information/component/ct-settings-captcha-select-v2';

const forbiddenBusinessModules = [
    /^ct-(?:bulk-edit|cms|customer|manufacturer|newsletter|order|payments|product|promotion|property|review|sales-channel)/,
    /^ct-settings-(?:cart|customer-group|delivery-times|document|login-registration|newsletter|payment|product-feature-sets|services|shipping|store|tax)/,
];

const forbiddenRemoteExtensionSources = [
    'ct-extension-sdk/index.js',
    'ct-extension/component/ct-extension-app-module-error-page/index.ts',
    'ct-extension/component/ct-extension-card-bought/index.js',
    'ct-extension/component/ct-extension-deactivation-modal/index.js',
    'ct-extension/component/ct-ratings/ct-extension-rating-modal/index.js',
    'ct-extension/page/ct-extension-app-module-page/index.ts',
    'ct-extension/page/ct-extension-my-extensions-recommendation/index.js',
    'ct-extension/page/ct-extension-store-landing-page/index.js',
];

describe('Contena core Administration modules', () => {
    it('contains only unique and sorted module names', () => {
        expect([...CONTENA_CORE_MODULES]).toEqual([...new Set(CONTENA_CORE_MODULES)].sort());
    });

    it('keeps the generic Administration capabilities', () => {
        expect(CONTENA_CORE_MODULES).toEqual(
            expect.arrayContaining([
                'ct-blog',
                'ct-channel',
                'ct-category',
                'ct-dashboard',
                'ct-extension',
                'ct-experience-studio',
                'ct-flow',
                'ct-integration',
                'ct-landing-page',
                'ct-mail-template',
                'ct-media',
                'ct-permissions',
                'ct-settings',
                'ct-settings-basic-information',
                'ct-settings-mailer',
                'ct-settings-rule',
                'ct-settings-search',
                'ct-settings-sitemap',
                'ct-users',
            ]),
        );
    });

    it.each([
        flowDetail,
        flowIndex,
        flowMailSendModal,
        flowSequenceEditor,
        ruleConditionEditor,
        ruleDetail,
        ruleList,
        listing,
        listingDeleteModal,
        listingOptionBase,
        listingOptionCreate,
        listingOptionCriteriaGrid,
        listingOptionGeneralInfo,
        seoMainCategory,
        seoUrl,
        seoUrlTemplateCard,
        settingsSeo,
        settingsBasicInformation,
        settingsCaptchaSelect,
    ])('exports core pages as renderable SFC component configurations', (component) => {
        expect(component._renderedBySfcTemplate).toBe(true);
        expect(component.render).toEqual(expect.any(Function));
    });

    it.each(forbiddenBusinessModules)('does not bundle business modules matching %s', (forbiddenModule) => {
        expect(CONTENA_CORE_MODULES).not.toEqual(expect.arrayContaining([expect.stringMatching(forbiddenModule)]));
    });

    it.each(forbiddenRemoteExtensionSources)('does not ship the remote extension source %s', (sourcePath) => {
        expect(existsSync(resolve(__dirname, sourcePath))).toBe(false);
    });
});
