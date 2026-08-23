<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\MailTemplateTypes;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Content\MailTemplate\Subscriber\UserRecoveryMailSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(UserRecoveryMailSubscriber::class)]
class UserRecoveryMailSubscriberTest extends TestCase
{
    public function testItSendsTheSystemDefaultRecoveryTemplate(): void
    {
        $context = Context::createDefaultContext();
        $user = new UserEntity()->assign([
            'id' => Uuid::randomHex(),
            'email' => 'admin@example.com',
            'name' => 'Contena Administrator',
        ]);
        $recovery = new UserRecoveryEntity()->assign([
            'id' => Uuid::randomHex(),
            'userId' => $user->getId(),
            'hash' => 'recovery-hash',
            'user' => $user,
        ]);
        $template = new MailTemplateEntity()->assign([
            'id' => Uuid::randomHex(),
            'systemDefault' => true,
            'senderName' => 'Contena',
            'subject' => 'Reset your password',
            'contentHtml' => '<p>Reset</p>',
            'contentPlain' => 'Reset',
        ]);

        /** @var StaticEntityRepository<MailTemplateCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria, Context $searchContext) use ($context, $template): MailTemplateCollection {
                static::assertSame($context, $searchContext);
                static::assertSame(1, $criteria->getLimit());
                static::assertSame('user-recovery::load-mail-template', $criteria->getTitle());
                static::assertTrue($criteria->hasAssociation('mailTemplateType'));
                static::assertTrue($criteria->hasAssociation('media'));
                static::assertTrue(self::hasEqualsFilter(
                    $criteria,
                    'mailTemplateType.technicalName',
                    MailTemplateTypes::MAILTYPE_USER_RECOVERY_REQUEST,
                ));
                static::assertTrue(self::hasEqualsFilter($criteria, 'systemDefault', true));

                return new MailTemplateCollection([$template]);
            },
        ]);
        $sendService = $this->createMock(MailTemplateSendService::class);
        $sendService->expects($this->once())->method('send')->willReturnCallback(
            static function (
                MailPayload $payload,
                Context $sendContext,
                array $templateData,
                MailTemplateEntity $sentTemplate,
            ) use ($context, $recovery, $template): null {
                static::assertSame(['admin@example.com' => 'Contena Administrator'], $payload->recipients);
                static::assertSame('<p>Reset</p>', $payload->contentHtml);
                static::assertSame('Reset', $payload->contentPlain);
                static::assertSame('Reset your password', $payload->subject);
                static::assertSame('Contena', $payload->senderName);
                static::assertSame($context, $sendContext);
                static::assertSame($recovery, $templateData['userRecovery']);
                static::assertSame('https://example.com/reset/recovery-hash', $templateData['resetUrl']);
                static::assertSame($template, $sentTemplate);

                return null;
            },
        );

        new UserRecoveryMailSubscriber($repository, $sendService)->sendRecoveryMail(
            new UserRecoveryRequestEvent($recovery, 'https://example.com/reset/recovery-hash', $context),
        );
    }

    public function testItDoesNotSendWhenNoSystemDefaultTemplateExists(): void
    {
        /** @var StaticEntityRepository<MailTemplateCollection> $repository */
        $repository = new StaticEntityRepository([new MailTemplateCollection()]);
        $sendService = $this->createMock(MailTemplateSendService::class);
        $sendService->expects($this->never())->method('send');

        $user = new UserEntity()->assign([
            'id' => Uuid::randomHex(),
            'email' => 'admin@example.com',
            'name' => 'Contena Administrator',
        ]);
        $recovery = new UserRecoveryEntity()->assign([
            'id' => Uuid::randomHex(),
            'userId' => $user->getId(),
            'hash' => 'recovery-hash',
            'user' => $user,
        ]);
        $context = Context::createDefaultContext();

        new UserRecoveryMailSubscriber($repository, $sendService)->sendRecoveryMail(
            new UserRecoveryRequestEvent($recovery, 'https://example.com/reset/recovery-hash', $context),
        );
    }

    private static function hasEqualsFilter(Criteria $criteria, string $field, string|bool $value): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === $field && $filter->getValue() === $value) {
                return true;
            }
        }

        return false;
    }
}
