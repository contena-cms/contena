<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Controller\ApiController;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController\ChildDefinition;
use Contena\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController\ParentDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ApiController::class)]
class ApiControllerTest extends TestCase
{
    public function testListWithOneToOneAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondChildOneToOneParent.id', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            static::createStub(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-child-one-to-one', $parentId)
        );
    }

    public function testListWithManyToOneAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondManyToOneParents.id', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            static::createStub(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-child-many-to-one', $parentId)
        );
    }

    public function testListWithOneToManyAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondParentOneToManyId', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            static::createStub(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-one-to-many-children', $parentId)
        );
    }

    private function createApiController(
        string $expectedFilterField,
        string $parentId,
    ): ApiController {
        $container = $this->createContainer($expectedFilterField, $parentId);

        $definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [ParentDefinition::class, ChildDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            $container
        );

        $aggregationParser = new AggregationParser();

        $requestCriteriaBuilder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($definitionInstanceRegistry),
            new CriteriaArrayConverter($aggregationParser),
            static::createStub(CompressedCriteriaDecoder::class)
        );

        return new ApiController(
            $definitionInstanceRegistry,
            static::createStub(DecoderInterface::class),
            $requestCriteriaBuilder,
            static::createStub(EntityProtectionValidator::class),
            static::createStub(AclCriteriaValidator::class)
        );
    }

    private function createContainer(string $expectedFilterField, string $parentId): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $parentDefinition = new Definition(StaticEntityRepository::class);
        $parentDefinition->setArguments([[], new ParentDefinition()]);

        $childDefinition = new Definition(StaticEntityRepository::class);
        $childDefinition->setArguments([[], new ChildDefinition()]);

        $container->setDefinitions([
            'parent_entity.repository' => $parentDefinition,
            'child_entity.repository' => $childDefinition,
        ]);

        $container->set('parent_entity.repository', static::createStub(EntityRepository::class));

        $childRepo = static::createStub(EntityRepository::class);
        $childRepo->method('search')->willReturnCallback(static function (Criteria $criteria, Context $context) use ($expectedFilterField, $parentId): EntitySearchResult {
            $filter = $criteria->getFilters()[0];
            static::assertInstanceOf(EqualsFilter::class, $filter);
            static::assertSame($expectedFilterField, $filter->getField());
            static::assertSame($parentId, $filter->getValue());

            return new EntitySearchResult(
                0,
                new EntityCollection(),
                null,
                $criteria,
                $context
            );
        });
        $container->set('child_entity.repository', $childRepo);

        return $container;
    }
}
