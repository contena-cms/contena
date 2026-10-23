/**
 * @sw-package discovery
 */

const { Application, Defaults } = Contena;

Application.addServiceProvider('domainLinkService', () => {
    return {
        getDomainLink,
    };
});

function getDomainLink(channel) {
    if (channel.type.id !== Defaults.frontendSalesChannelTypeId) {
        return null;
    }

    if (channel.domains.length === 0) {
        return null;
    }

    const adminLanguageDomain = channel.domains.find((domain) => {
        return domain.languageId === Contena.Store.get('session').languageId;
    });

    if (adminLanguageDomain) {
        return adminLanguageDomain.url;
    }

    const systemLanguageDomain = channel.domains.find((domain) => {
        return domain.languageId === Defaults.systemLanguageId;
    });

    if (systemLanguageDomain) {
        return systemLanguageDomain.url;
    }

    return channel.domains[0].url;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { getDomainLink as default };
