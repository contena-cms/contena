<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\BaseChannelContext;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;

/**
 * @phpstan-import-type BaseContextOptions from ContextFactory
 *
 * @phpstan-type ContextOptions array{
 *     originalContext?: Context,
 *     version-id?: string,
 *     languageId?: string,
 *     countryId?: string,
 *     domainId?: string,
 * }
 *
 * @internal
 */
class BaseChannelContextFactory extends AbstractBaseChannelContextFactory
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<MemberGroupCollection> $memberGroupRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<EntityCollection<PartialEntity>> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
        private readonly EntityRepository $memberGroupRepository,
        private readonly EntityRepository $countryRepository,
        private readonly ContextFactory $contextFactory,
        private readonly EntityRepository $languageRepository,
    ) {
    }

    /**
     * @param ContextOptions $options
     */
    public function create(string $channelId, array $options = []): BaseChannelContext
    {
        $context = $this->contextFactory->getContext($channelId, $this->getBaseContextOptions($options));

        $criteria = new Criteria([$channelId]);
        $criteria->setTitle('base-context-factory::channel');
        $criteria->addAssociation('domains');

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->get($channelId);
        if (!$channel instanceof ChannelEntity) {
            throw ChannelException::channelNotFound($channelId);
        }

        $groupId = $channel->getMemberGroupId();
        $criteria = new Criteria([$groupId]);
        $criteria->setTitle('base-context-factory::member-group');

        $memberGroup = $this->memberGroupRepository->search($criteria, $context)->getEntities()->get($groupId);
        if (!$memberGroup instanceof MemberGroupEntity) {
            throw ChannelException::memberGroupNotFound($groupId);
        }

        $countryId = $options[ChannelContextService::COUNTRY_ID] ?? $channel->getCountryId();
        if (!\is_string($countryId) || !Uuid::isValid($countryId)) {
            throw ChannelException::invalidCountryId();
        }

        $criteria = new Criteria([$countryId]);
        $criteria->setTitle('base-context-factory::country');
        $country = $this->countryRepository->search($criteria, $context)->getEntities()->get($countryId);
        if (!$country instanceof CountryEntity) {
            throw ChannelException::countryNotFound($countryId);
        }

        return new BaseChannelContext(
            $context,
            $channel,
            $memberGroup,
            $country,
            $this->getLanguageInfo($context),
        );
    }

    private function getLanguageInfo(Context $context): LanguageInfo
    {
        $currentLanguageId = $context->getLanguageId();
        $criteria = new Criteria([$currentLanguageId])->addFields([
            'name',
            'translationCode.code',
            'locale.code',
        ]);

        $currentLanguage = $this->languageRepository->search($criteria, $context)->getEntities()->get($currentLanguageId);
        if (!$currentLanguage instanceof PartialEntity) {
            throw ChannelException::languageNotFound($currentLanguageId);
        }

        $locale = $currentLanguage->get('translationCode') ?? $currentLanguage->get('locale');
        \assert($locale instanceof PartialEntity, 'At least the localeId is required, so the fallback should never be null');

        return new LanguageInfo(
            $currentLanguage->get('name'),
            $locale->get('code'),
        );
    }

    /**
     * @param ContextOptions $options
     *
     * @return BaseContextOptions
     */
    private function getBaseContextOptions(array $options): array
    {
        $contextOptions = [];
        if (\array_key_exists(ChannelContextService::ORIGINAL_CONTEXT, $options)) {
            $contextOptions[ChannelContextService::ORIGINAL_CONTEXT] = $options[ChannelContextService::ORIGINAL_CONTEXT];
        }
        if (\array_key_exists(ChannelContextService::VERSION_ID, $options)) {
            $contextOptions[ChannelContextService::VERSION_ID] = $options[ChannelContextService::VERSION_ID];
        }
        if (\array_key_exists(ChannelContextService::LANGUAGE_ID, $options)) {
            $contextOptions[ChannelContextService::LANGUAGE_ID] = $options[ChannelContextService::LANGUAGE_ID];
        }

        return $contextOptions;
    }
}
