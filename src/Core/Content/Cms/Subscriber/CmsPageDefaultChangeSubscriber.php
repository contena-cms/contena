<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CmsPageDefaultChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string>
     */
    public static array $defaultCmsPageConfigKeys = [
        BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG,
        CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'validateChangeOfDefaultCmsPage',
            EntityDeleteEvent::class => 'beforeDeletion',
        ];
    }

    /**
     * @throws CmsException
     * @throws \JsonException
     */
    public function beforeDeletion(EntityDeleteEvent $event): void
    {
        // validate only deletions on the live version
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var array<string> $cmsPageIds */
        $cmsPageIds = $event->getIds(CmsPageDefinition::ENTITY_NAME);

        // no cms page is affected by this deletion event
        if ($cmsPageIds === []) {
            return;
        }

        $defaultPages = $this->cmsPageIsDefault($cmsPageIds);

        // count !== 0 indicates that there are some cms pages which would be deleted but are currently a default
        if ($defaultPages !== []) {
            throw CmsException::deletionOfDefault($defaultPages);
        }
    }

    /**
     * @throws CmsException
     * @throws PageNotFoundException
     */
    public function validateChangeOfDefaultCmsPage(BeforeSystemConfigChangedEvent $event): void
    {
        $systemConfigKey = $event->getKey();

        if (!\in_array($systemConfigKey, self::$defaultCmsPageConfigKeys, true)) {
            return;
        }

        $newDefaultCmsPageId = $event->getValue();
        $channelId = $event->getChannelId();

        // prevent deleting the overall default (channelId === null)
        // a sales channel specific default can still be deleted (channelId !== null)
        if ($newDefaultCmsPageId === null && $channelId === null) {
            $oldCmsPageId = $this->getCurrentOverallDefaultCmsPageId($systemConfigKey);

            throw CmsException::overallDefaultSystemConfigDeletion($oldCmsPageId);
        }

        if (!\is_string($newDefaultCmsPageId) && $newDefaultCmsPageId !== null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore contena.domainException (Will be fixed with next major) */
                throw new PageNotFoundException('invalid page');
            }
            throw CmsException::pageNotFound('invalid page');
        }

        // prevent changing the default to an invalid cms page id
        if (\is_string($newDefaultCmsPageId) && !$this->cmsPageExists($newDefaultCmsPageId)) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore contena.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($newDefaultCmsPageId);
            }
            throw CmsException::pageNotFound($newDefaultCmsPageId);
        }
    }

    private function getCurrentOverallDefaultCmsPageId(string $systemConfigKey): string
    {
        $result = $this->connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey AND channel_id is NULL;',
            [
                'configKey' => $systemConfigKey,
            ]
        );

        $config = json_decode((string) $result, true, 512, \JSON_THROW_ON_ERROR);

        return $config['_value'];
    }

    /**
     * @param array<string> $cmsPageIds
     *
     * @return array<string>
     */
    private function cmsPageIsDefault(array $cmsPageIds): array
    {
        $configurations = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT configuration_value FROM system_config WHERE configuration_key IN (:configKeys);',
            [
                'configKeys' => self::$defaultCmsPageConfigKeys,
            ],
            [
                'configKeys' => ArrayParameterType::STRING,
            ]
        );

        $defaultIds = [];
        foreach ($configurations as $configuration) {
            $configValue = $configuration['configuration_value'];
            $config = json_decode((string) $configValue, true, 512, \JSON_THROW_ON_ERROR);

            $defaultIds[] = $config['_value'];
        }

        // returns from all provided cms pages the ones which are default
        return array_intersect($cmsPageIds, $defaultIds);
    }

    private function cmsPageExists(string $cmsPageId): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT count(*) FROM cms_page WHERE id = :cmsPageId AND version_id = :versionId LIMIT 1;',
            [
                'cmsPageId' => Uuid::fromHexToBytes($cmsPageId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        return $count === '1';
    }
}
