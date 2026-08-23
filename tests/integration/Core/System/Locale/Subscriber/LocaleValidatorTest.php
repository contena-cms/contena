<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Locale\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\Exception\InvalidLocaleCodeException;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Locale\Subscriber\LocaleValidator;

/**
 * @internal
 */
class LocaleValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->localeRepository = static::getContainer()->get('locale.repository');
        $this->definitionInstanceRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
    }

    public function testItCannotCreateLocaleWithInvalidCode(): void
    {
        try {
            $this->localeRepository->create([
                [
                    'code' => 'foo_BAR',
                    'name' => 'English',
                    'territory' => 'USA',
                ],
            ], Context::createDefaultContext());
        } catch (WriteException $e) {
            static::assertInstanceOf(InvalidLocaleCodeException::class, $e->getExceptions()[0]);
            static::assertSame(
                'Cannot create or update locale with invalid code "foo_BAR"',
                $e->getExceptions()[0]->getMessage()
            );

            return;
        }

        static::fail('WriteException not thrown');
    }

    public function testItValidatesAllDefaultLocalesWithoutErrors(): void
    {
        $locales = $this->localeRepository->search(new Criteria(), Context::createDefaultContext())->getEntities()->getElements();
        $definition = $this->definitionInstanceRegistry->get(LocaleDefinition::class);
        $entityExistinceMock = $this->createMock(EntityExistence::class);

        $commands = array_map(static fn (LocaleEntity $locale) => new UpdateCommand(
            $definition,
            ['code' => $locale->getCode()],
            ['id' => Uuid::fromHexToBytes($locale->getId())],
            $entityExistinceMock,
            '/0/'
        ), $locales);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            array_values($commands)
        );

        new LocaleValidator()->preWriteValidateEvent($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
