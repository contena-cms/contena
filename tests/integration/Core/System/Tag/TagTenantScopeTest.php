<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Tag;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tag\TagCollection;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\TenantIsolationTestTrait;

/**
 * @internal
 */
class TagTenantScopeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TenantIsolationTestTrait;

    public function testTagsAreIsolatedByTenant(): void
    {
        $tenantA = $this->seedTenant('tag-a');
        $tenantB = $this->seedTenant('tag-b');
        $name = 'tenant-scope-tag-' . \bin2hex(\random_bytes(4));

        $this->assertTenantIsolated(
            $tenantA,
            $tenantB,
            fn (string $tenantId): mixed => $this->tagRepository()->create([
                ['id' => Uuid::randomHex(), 'name' => $name],
            ], Context::createTenantContext($tenantId)),
            fn (Context $context): int => $this->tagRepository()->search(
                new Criteria()->addFilter(new EqualsFilter('name', $name)),
                $context,
            )->getTotal(),
        );
    }

    /**
     * @return EntityRepository<TagCollection>
     */
    private function tagRepository(): EntityRepository
    {
        return static::getContainer()->get('tag.repository');
    }
}
