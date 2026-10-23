<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\AccountNewsletterRecipientRouteExtension;
use Contena\Core\System\User\Channel\AccountNewsletterRecipientRoute;
use Contena\Core\System\User\Channel\AccountNewsletterRecipientRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountNewsletterRecipientRoute::class)]
class AccountNewsletterRecipientRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $user = new UserEntity();
        $response = static::createStub(AccountNewsletterRecipientRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('account-newsletter-recipient-route.load.pre', static function (AccountNewsletterRecipientRouteExtension $extension) use ($request, $context, $criteria, $user, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new AccountNewsletterRecipientRoute(
            static::createStub(ChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria, $user));
    }
}
