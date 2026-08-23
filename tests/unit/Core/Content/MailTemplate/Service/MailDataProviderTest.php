<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\MailTemplateException;
use Contena\Core\Content\MailTemplate\Service\MailDataProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MailDataProvider::class)]
class MailDataProviderTest extends TestCase
{
    public function testGetTemplateDataFiltersUnavailableEntitiesAndUsesProviderEntityName(): void
    {
        $context = Context::createDefaultContext();
        $userEntity = new MailTemplateEntity();
        $provider = $this->createProvider('user', $userEntity);

        $mailDataProvider = new MailDataProvider([
            'user' => $provider,
        ]);

        $result = $mailDataProvider->getTemplateData(
            $this->createMailTemplate(['user' => 'user']),
            [
                'user' => 'user-id',
                'media' => 'media-id',
            ],
            $context
        );

        static::assertSame(['user' => $userEntity], $result);
        static::assertSame(['user-id'], $provider->requestedIds);
        static::assertSame([$context], $provider->requestedContexts);
    }

    public function testGetTemplateDataReturnsInjectedTemplateDataWhenNoMailTemplateTypeExists(): void
    {
        $mailDataProvider = new MailDataProvider([]);

        $result = $mailDataProvider->getTemplateData(
            new MailTemplateEntity(),
            ['user' => 'user-id'],
            Context::createDefaultContext(),
            ['foo' => 'bar']
        );

        static::assertSame(['foo' => 'bar'], $result);
    }

    public function testGetTemplateDataAllowsInjectedTemplateDataToOverrideProvidedEntities(): void
    {
        $context = Context::createDefaultContext();
        $providerEntity = new MailTemplateEntity();
        $provider = $this->createProvider('user', $providerEntity);

        $mailDataProvider = new MailDataProvider([
            'user' => $provider,
        ]);

        $result = $mailDataProvider->getTemplateData(
            $this->createMailTemplate(['user' => 'user']),
            ['user' => 'user-id'],
            $context,
            ['user' => 'overridden', 'extra' => 'value']
        );

        static::assertSame(
            [
                'user' => 'overridden',
                'extra' => 'value',
            ],
            $result
        );
    }

    public function testGetTemplateDataThrowsWhenProviderIsMissingForAvailableEntity(): void
    {
        $mailDataProvider = new MailDataProvider([]);

        $this->expectExceptionObject(MailTemplateException::missingDataProvider('user'));

        $mailDataProvider->getTemplateData(
            $this->createMailTemplate(['user' => 'user']),
            ['user' => 'user-id'],
            Context::createDefaultContext(),
        );
    }

    /**
     * @param array<string, mixed>|null $availableEntities
     */
    private function createMailTemplate(?array $availableEntities): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setAvailableEntities($availableEntities);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);

        return $mailTemplate;
    }

    private function createProvider(string $entityName, ?Entity $entity): TestMailFlowProvider
    {
        return new TestMailFlowProvider(
            $entityName,
            $entity,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ContainerInterface::class)
        );
    }
}
/**
 * @internal
 *
 * @extends AbstractProvider<Entity, EntityCollection<Entity>>
 */
class TestMailFlowProvider extends AbstractProvider
{
    /**
     * @var list<string>
     */
    public array $requestedIds = [];

    /**
     * @var list<Context>
     */
    public array $requestedContexts = [];

    public function __construct(
        private readonly string $entityName,
        private readonly ?Entity $entity,
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ) {
        parent::__construct($eventDispatcher, $container);
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getData(string $entityId, Context $context): ?Entity
    {
        $this->requestedIds[] = $entityId;
        $this->requestedContexts[] = $context;

        return $this->entity;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
