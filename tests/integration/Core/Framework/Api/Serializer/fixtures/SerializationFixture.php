<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 */
abstract class SerializationFixture
{
    public const string API_BASE_URL = 'http://localhost/api';
    public const string CHANNEL_API_BASE_URL = 'http://localhost/channel-api';

    /**
     * @return EntityCollection<Entity>|Entity
     */
    abstract public function getInput(): EntityCollection|Entity;

    /**
     * @return array<string, mixed>
     */
    public function getAdminJsonApiFixtures(): array
    {
        $fixtures = $this->getJsonApiFixtures(self::API_BASE_URL);

        return $this->removeProtectedAdminJsonApiData($fixtures);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getChannelJsonApiFixtures(): array
    {
        $fixtures = $this->getJsonApiFixtures(self::CHANNEL_API_BASE_URL);

        return $this->removeProtectedChannelJsonApiData($fixtures);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getAdminJsonFixtures(): array
    {
        $fixtures = $this->getJsonFixtures();

        return $this->removeProtectedAdminJsonData($fixtures);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getChannelJsonFixtures(): array
    {
        $fixtures = $this->getJsonFixtures();

        return $this->removeProtectedChannelJsonData($fixtures);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function getJsonApiFixtures(string $baseUrl): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getJsonFixtures(): array;

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedChannelJsonApiData(array $fixtures): array
    {
        return $fixtures;
    }

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedAdminJsonApiData(array $fixtures): array
    {
        return $fixtures;
    }

    /**
     * @param array<int|string, mixed> $fixtures
     *
     * @return array<int|string, mixed>
     */
    protected function removeProtectedChannelJsonData(array $fixtures): array
    {
        return $fixtures;
    }

    /**
     * @param array<int|string, mixed> $fixtures
     *
     * @return array<int|string, mixed>
     */
    protected function removeProtectedAdminJsonData(array $fixtures): array
    {
        return $fixtures;
    }
}
