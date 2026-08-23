<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
class SendMailActionTenantTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testAdminRecipientsFollowTheFlowTenantContext(): void
    {
        $tenantA = $this->createTenant('Flow mail tenant A');
        $tenantB = $this->createTenant('Flow mail tenant B');
        $contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($tenantA->id),
            'tenant-b' => Context::createTenantContext($tenantB->id),
            'global' => Context::createGlobalContext(),
        ];
        $emails = [
            'platform' => $this->createAdminUser('platform', $contexts['platform']),
            'tenant-a' => $this->createAdminUser('tenant-a', $contexts['tenant-a']),
            'tenant-b' => $this->createAdminUser('tenant-b', $contexts['tenant-b']),
        ];

        foreach ($contexts as $scope => $context) {
            $payload = $this->executeAction($context);
            $expectedScope = $scope === 'global' ? 'platform' : $scope;

            static::assertArrayHasKey($emails[$expectedScope], $payload->recipients);
            foreach ($emails as $emailScope => $email) {
                if ($emailScope !== $expectedScope) {
                    static::assertArrayNotHasKey($email, $payload->recipients, $scope . ' included ' . $emailScope . ' admin');
                }
            }
        }
    }

    public function testFailureLogKeepsTheFlowTenant(): void
    {
        $tenant = $this->createTenant('Flow mail log tenant');
        $context = Context::createTenantContext($tenant->id);
        $this->createAdminUser('log-tenant', $context);
        $exception = new \RuntimeException('Mail transport failed');
        $mailService = $this->createMock(MailTemplateSendService::class);
        $mailService->method('send')->willThrowException($exception);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Could not send flow mail.', [
            'exception' => $exception,
            'flowEvent' => 'tenant.flow.event',
            'tenantId' => $tenant->id,
        ]);

        $this->action($mailService, $logger)->handleFlow($this->flow($context));
    }

    private function executeAction(Context $context): MailPayload
    {
        $payload = null;
        $mailService = $this->createMock(MailTemplateSendService::class);
        $mailService->expects($this->once())->method('send')
            ->willReturnCallback(static function (MailPayload $mailPayload) use (&$payload): null {
                $payload = $mailPayload;

                return null;
            });

        $this->action($mailService, static::createStub(LoggerInterface::class))->handleFlow($this->flow($context));
        static::assertInstanceOf(MailPayload::class, $payload);

        return $payload;
    }

    private function flow(Context $context): StorableFlow
    {
        $flow = new StorableFlow('tenant.flow.event', $context, [], [
            MailAware::MAIL_STRUCT => new MailRecipientStruct([]),
        ]);
        $flow->setConfig([
            'mailTemplateId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'recipient' => ['type' => 'admin'],
        ]);

        return $flow;
    }

    private function action(MailTemplateSendService $mailService, LoggerInterface $logger): SendMailAction
    {
        return new SendMailAction(
            $mailService,
            $this->templateRepository(),
            $this->userRepository(),
            $logger,
        );
    }

    /**
     * @return EntityRepository<MailTemplateCollection>
     */
    private function templateRepository(): EntityRepository
    {
        $template = new MailTemplateEntity();
        $template->setUniqueIdentifier('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $template->setSubject('Tenant flow mail');
        $template->setContentHtml('<p>Tenant flow mail</p>');
        $template->setContentPlain('Tenant flow mail');
        $template->setSenderName('Contena');

        /** @var EntityRepository<MailTemplateCollection>&Stub $repository */
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(static fn (Criteria $criteria, Context $context): EntitySearchResult => new EntitySearchResult(
            1,
            new MailTemplateCollection([$template]),
            null,
            $criteria,
            $context,
        ));

        return $repository;
    }

    private function createAdminUser(string $scope, Context $context): string
    {
        $suffix = \bin2hex(\random_bytes(6));
        $email = 'flow-mail-' . $scope . '-' . $suffix . '@example.invalid';
        $localeId = $this->connection()->fetchOne('SELECT LOWER(HEX(`id`)) FROM `locale` LIMIT 1');
        static::assertIsString($localeId);

        $this->repository('user')->create([[
            'id' => Uuid::randomHex(),
            'userCode' => 'flow-mail-' . $scope . '-' . $suffix,
            'username' => 'flow-mail-' . $scope . '-' . $suffix,
            'password' => 'integration-test-password',
            'name' => 'Flow mail ' . $scope,
            'email' => $email,
            'localeId' => $localeId,
            'active' => true,
            'admin' => true,
        ]], $context);

        return $email;
    }

    /**
     * @return EntityRepository<UserCollection>
     */
    private function userRepository(): EntityRepository
    {
        /** @var EntityRepository<UserCollection> $repository */
        $repository = static::getContainer()->get('user.repository');

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
