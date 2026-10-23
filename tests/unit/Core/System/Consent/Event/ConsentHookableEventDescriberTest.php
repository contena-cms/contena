<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Contena\Core\System\Consent\ConsentDefinitionRegistry;
use Contena\Core\System\Consent\Event\ConsentHookableEventDescriber;

/**
 * @internal
 */
#[CoversClass(ConsentHookableEventDescriber::class)]
class ConsentHookableEventDescriberTest extends TestCase
{
    public function testResolveReturnsConsentWebhookPrivileges(): void
    {
        $resolver = new ConsentHookableEventDescriber(new ConsentDefinitionRegistry([
        ]));

        static::assertEquals([
            new HookableEventDescription('consent.backend_data.accepted', 'Fires when the backend_data consent is accepted.', ['consent:backend_data:read']),
            new HookableEventDescription('consent.backend_data.revoked', 'Fires when the backend_data consent is revoked.', ['consent:backend_data:read']),
            new HookableEventDescription('consent.blog_analytics.accepted', 'Fires when the blog_analytics consent is accepted.', ['consent:blog_analytics:read']),
            new HookableEventDescription('consent.blog_analytics.revoked', 'Fires when the blog_analytics consent is revoked.', ['consent:blog_analytics:read']),
        ], $resolver->describe());
    }
}
