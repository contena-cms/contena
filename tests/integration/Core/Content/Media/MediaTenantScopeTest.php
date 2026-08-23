<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\TenantIsolationTestTrait;

/**
 * @internal
 */
class MediaTenantScopeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TenantIsolationTestTrait;

    public function testMediaIsIsolatedByTenant(): void
    {
        $tenantA = $this->seedTenant('media-a');
        $tenantB = $this->seedTenant('media-b');
        $fileName = 'tenant-scope-media-' . \bin2hex(\random_bytes(4)) . '.png';

        $this->assertTenantIsolated(
            $tenantA,
            $tenantB,
            fn (string $tenantId): mixed => $this->mediaRepository()->create([
                ['id' => Uuid::randomHex(), 'fileName' => $fileName, 'mimeType' => 'image/png'],
            ], Context::createTenantContext($tenantId)),
            fn (Context $context): int => $this->mediaRepository()->search(
                new Criteria()->addFilter(new EqualsFilter('fileName', $fileName)),
                $context,
            )->getTotal(),
        );
    }

    /**
     * @return EntityRepository<MediaCollection>
     */
    private function mediaRepository(): EntityRepository
    {
        return static::getContainer()->get('media.repository');
    }
}
