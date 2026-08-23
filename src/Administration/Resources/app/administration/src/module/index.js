const importLogin = () => {
    return import.meta.glob('./ct-login/index!(*.spec).{j,t}s', {
        eager: true,
    });
};

// Keep this list explicit. Vite needs static glob patterns so excluded business modules
// are not bundled into the generic Contena Administration release.
/** @private */
export const CONTENA_CORE_MODULES = Object.freeze([
    'ct-blog',
    'ct-category',
    'ct-channel',
    'ct-dashboard',
    'ct-data-dictionary',
    'ct-experience-studio',
    'ct-extension',
    'ct-flow',
    'ct-integration',
    'ct-landing-page',
    'ct-mail-template',
    'ct-media',
    'ct-member',
    'ct-permissions',
    'ct-privilege-error',
    'ct-profile',
    'ct-settings',
    'ct-settings-basic-information',
    'ct-settings-cache',
    'ct-settings-country',
    'ct-settings-custom-field',
    'ct-settings-language',
    'ct-settings-listing',
    'ct-settings-logging',
    'ct-settings-mailer',
    'ct-settings-media',
    'ct-settings-member-group',
    'ct-settings-message-stats',
    'ct-settings-organization',
    'ct-settings-position',
    'ct-settings-region',
    'ct-settings-rule',
    'ct-settings-search',
    'ct-settings-seo',
    'ct-settings-sitemap',
    'ct-settings-snippet',
    'ct-settings-tag',
    'ct-users',
]);

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default async () => {
    const context = await import.meta.glob([
        './ct-blog/index!(*.spec).{j,t}s',
        './ct-dashboard/index!(*.spec).{j,t}s',
        './ct-category/index!(*.spec).{j,t}s',
        './ct-channel/index!(*.spec).{j,t}s',
        './ct-extension/index!(*.spec).{j,t}s',
        './ct-experience-studio/index!(*.spec).{j,t}s',
        './ct-flow/index!(*.spec).{j,t}s',
        './ct-integration/index!(*.spec).{j,t}s',
        './ct-landing-page/index!(*.spec).{j,t}s',
        './ct-mail-template/index!(*.spec).{j,t}s',
        './ct-media/index!(*.spec).{j,t}s',
        './ct-member/index!(*.spec).{j,t}s',
        './ct-privilege-error/index!(*.spec).{j,t}s',
        './ct-profile/index!(*.spec).{j,t}s',
        './ct-settings/index!(*.spec).{j,t}s',
        './ct-settings-basic-information/index!(*.spec).{j,t}s',
        './ct-settings-cache/index!(*.spec).{j,t}s',
        './ct-settings-country/index!(*.spec).{j,t}s',
        './ct-settings-custom-field/index!(*.spec).{j,t}s',
        './ct-settings-language/index!(*.spec).{j,t}s',
        './ct-settings-logging/index!(*.spec).{j,t}s',
        './ct-settings-listing/index!(*.spec).{j,t}s',
        './ct-settings-mailer/index!(*.spec).{j,t}s',
        './ct-settings-rule/index!(*.spec).{j,t}s',
        './ct-settings-search/index!(*.spec).{j,t}s',
        './ct-settings-media/index!(*.spec).{j,t}s',
        './ct-settings-member-group/index!(*.spec).{j,t}s',
        './ct-settings-message-stats/index!(*.spec).{j,t}s',
        './ct-settings-organization/index!(*.spec).{j,t}s',
        './ct-settings-position/index!(*.spec).{j,t}s',
        './ct-settings-region/index!(*.spec).{j,t}s',
        './ct-settings-seo/index!(*.spec).{j,t}s',
        './ct-settings-sitemap/index!(*.spec).{j,t}s',
        './ct-settings-snippet/index!(*.spec).{j,t}s',
        './ct-settings-tag/index!(*.spec).{j,t}s',
        './ct-data-dictionary/index!(*.spec).{j,t}s',
        './ct-users/index!(*.spec).{j,t}s',
        './ct-permissions/index!(*.spec).{j,t}s',
    ]);

    const modules = Object.values(context)
        .reverse()
        .map((module) => module());

    return Promise.all(modules);
};

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export const login = () => {
    return Object.values(importLogin());
};
