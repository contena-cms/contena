<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Service\AbstractMailService;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(SsoUserInvitationMailService::class)]
class SsoUserInvitationMailServiceTest extends TestCase
{
    public function testSendInvitationMailToUser(): void
    {
        $abstractMailService = $this->createMock(AbstractMailService::class);
        $abstractMailService->expects($this->once())
            ->method('send')
            ->with(static::callback(static function (array $data) {
                self::assertNull($data['senderEmail']);
                self::assertSame('ShopName', $data['senderName']);

                return true;
            }));

        $systemConfigService = new StaticSystemConfigService([
            'core.basicInformation.shopName' => 'ShopName',
        ]);

        $mailTemplateEntity = new MailTemplateEntity();
        $mailTemplateEntity->setUniqueIdentifier(Uuid::randomHex());
        $mailTemplateEntity->setId(Uuid::randomHex());
        $mailTemplateRepository = new StaticEntityRepository([
            new MailTemplateCollection([$mailTemplateEntity]),
        ], new MailTemplateDefinition());

        $mailTemplateTypeEntity = new MailTemplateTypeEntity();
        $mailTemplateTypeEntity->setUniqueIdentifier(Uuid::randomHex());
        $mailTemplateTypeEntity->setId(Uuid::randomHex());
        $mailTemplateTypeRepository = new StaticEntityRepository([
            new MailTemplateTypeCollection([$mailTemplateTypeEntity]),
        ], new MailTemplateTypeDefinition());

        $adminUserEntity = new AdminUserEntity();
        $adminUserEntity->setUniqueIdentifier(Uuid::randomHex());
        $adminUserEntity->setName('FirstName');
        $adminUserEntity->setLastName('LastName');
        $adminUserEntity->setUsername('AdminUserName');
        $adminUserRepository = new StaticEntityRepository([
            new AdminUserCollection([$adminUserEntity]),
        ], new AdminUserDefinition());

        $languageEntity = new LanguageEntity();
        $languageEntity->setUniqueIdentifier(Uuid::randomHex());
        $languageEntity->setId(Uuid::randomHex());
        $languageRepository = new StaticEntityRepository([
            new LanguageCollection([$languageEntity]),
        ], new LanguageDefinition());

        $routerMock = $this->createMock(UrlGeneratorInterface::class);
        $routerMock->expects($this->once())->method('generate')->willReturn('/admin');

        $ssoUserInvitationMailService = new SsoUserInvitationMailService(
            $abstractMailService,
            $systemConfigService,
            $mailTemplateRepository,
            $mailTemplateTypeRepository,
            $adminUserRepository,
            $languageRepository,
            $routerMock,
            'app.url'
        );

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex(), null));

        $ssoUserInvitationMailService->sendInvitationMailToUser('test@test.com', Uuid::randomHex(), $context);
    }
}
