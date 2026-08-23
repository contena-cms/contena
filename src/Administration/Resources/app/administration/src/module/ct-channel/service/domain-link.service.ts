import type { SubContainer } from 'src/global.types';

const WEB_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

type DomainLinkService = {
    getDomainLink: typeof getDomainLink;
};

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        domainLinkService: DomainLinkService;
    }
}

/** @private */
export function getDomainLink(channel: Entity<'channel'>): string | null {
    if (channel.type?.id !== WEB_CHANNEL_TYPE_ID || !channel.domains?.length) {
        return null;
    }

    const sessionLanguageId = Contena.Store.get('session').languageId;
    const administrationLanguageDomain = channel.domains.find(
        (domain: Entity<'channel_domain'>) => domain.languageId === sessionLanguageId,
    );
    if (administrationLanguageDomain) {
        return administrationLanguageDomain.url;
    }

    const systemLanguageDomain = channel.domains.find(
        (domain: Entity<'channel_domain'>) => domain.languageId === Contena.Defaults.systemLanguageId,
    );

    return systemLanguageDomain?.url ?? channel.domains.first()?.url ?? null;
}

Contena.Application.addServiceProvider('domainLinkService', () => ({ getDomainLink }));
