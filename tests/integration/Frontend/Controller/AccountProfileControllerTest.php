<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
class AccountProfileControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;
    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    public function testProfileUsesMemberIdentityFields(): void
    {
        $context = Context::createDefaultContext();
        $member = $this->createMember($context);
        $browser = $this->login($member->getEmail());

        $browser->request('GET', '/account/profile');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertStringContainsString('name="name"', $content);
        static::assertStringContainsString('name="phoneNumber"', $content);
        static::assertStringNotContainsString('name="accountType"', $content);
        static::assertStringNotContainsString('name="firstName"', $content);
        static::assertStringNotContainsString('name="lastName"', $content);
    }

    public function testDeleteMemberProfile(): void
    {
        $context = Context::createDefaultContext();
        $member = $this->createMember($context);
        $browser = $this->login($member->getEmail());

        $browser->request('POST', $_SERVER['APP_URL'] . '/account/profile/delete');

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertTrue($browser->getResponse()->isRedirect(), (string) $browser->getResponse()->getContent());
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('POST', $_SERVER['APP_URL'] . '/account/login', $this->tokenize('frontend.account.login', [
            'username' => $email,
            'password' => 'contenaAdmin',
        ]));

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        return $browser;
    }

    private function createMember(Context $context): MemberEntity
    {
        $memberId = Uuid::randomHex();
        $member = [
            'id' => $memberId,
            'channelId' => self::DEFAULT_WEB_CHANNEL_ID,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ];

        /** @var EntityRepository<MemberCollection> $repository */
        $repository = static::getContainer()->get('member.repository');
        $repository->create([$member], $context);

        $memberEntity = $repository->search(new Criteria([$memberId]), $context)->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $memberEntity);

        return $memberEntity;
    }

    private function getFlashBag(): FlashBagInterface
    {
        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);

        return $session->getFlashBag();
    }
}
