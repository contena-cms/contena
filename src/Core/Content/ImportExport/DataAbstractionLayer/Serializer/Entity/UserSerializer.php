<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\UserDefinition;
use Symfony\Contracts\Service\ResetInterface;

class UserSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param array<string, string|null> $cacheUserGroups
     * @param array<string, string|null> $cacheChannels
     */
    public function __construct(
        private readonly EntityRepository $userGroupRepository,
        private readonly EntityRepository $channelRepository,
        private array $cacheUserGroups = [],
        private array $cacheChannels = [],
    ) {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        $context = Context::createDefaultContext();

        if (!isset($deserialized['groupId']) && isset($entity['group'])) {
            $name = $entity['group']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['group']['id'] ?? $this->getUserGroupId($name, $context);

            if ($id) {
                $deserialized['group']['id'] = $id;
            }
        }

        if (!isset($deserialized['channelId']) && isset($entity['channel'])) {
            $name = $entity['channel']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['channel']['id'] ?? $this->getChannelId($name, $context);

            if ($id) {
                $deserialized['channel']['id'] = $id;
            }
        }

        if (!isset($deserialized['boundChannelId']) && isset($entity['boundChannel'])) {
            $name = $entity['boundChannel']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['boundChannel']['id'] ?? $this->getChannelId($name, $context);

            if ($id) {
                $deserialized['boundChannel']['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === UserDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheUserGroups = [];
        $this->cacheChannels = [];
    }

    private function getUserGroupId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cacheUserGroups)) {
            return $this->cacheUserGroups[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $this->cacheUserGroups[$name] = $this->userGroupRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheUserGroups[$name];
    }

    private function getChannelId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cacheChannels)) {
            return $this->cacheChannels[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        $this->cacheChannels[$name] = $this->channelRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheChannels[$name];
    }
}
