<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Permission\PermissionsConsent;
use Contena\Core\Service\ServiceException;

/**
 * @internal
 */
#[CoversClass(PermissionsConsent::class)]
class PermissionsConsentTest extends TestCase
{
    public function testFromJsonStringWithValidData(): void
    {
        $json = (string) json_encode([
            'identifier' => 'service-123',
            'revision' => '2025-07-01',
            'consentingUserId' => 'admin_user-456',
            'grantedAt' => '2025-07-01 10:00:00',
        ]);

        $consent = PermissionsConsent::fromJsonString($json);

        static::assertSame('service-123', $consent->identifier);
        static::assertSame('2025-07-01', $consent->revision);
        static::assertSame('admin_user-456', $consent->consentingUserId);
        static::assertEquals(new \DateTime('2025-07-01 10:00:00'), $consent->grantedAt);
    }

    public function testFromJsonStringWithInvalidJson(): void
    {
        $this->expectException(ServiceException::class);
        PermissionsConsent::fromJsonString('invalid json');
    }

    public function testFromJsonStringWithNonArrayJson(): void
    {
        $this->expectException(ServiceException::class);
        PermissionsConsent::fromJsonString('"string"');
    }

    public function testFromJsonStringWithMissingIdentifier(): void
    {
        $json = (string) json_encode([
            'revision' => '2025-07-01',
            'consentingUserId' => 'admin_user-456',
            'grantedAt' => '2025-07-01 10:00:00',
        ]);

        $this->expectException(ServiceException::class);
        PermissionsConsent::fromJsonString($json);
    }

    public function testFromJsonStringWithMissingRevision(): void
    {
        $json = (string) json_encode([
            'identifier' => 'service-123',
            'consentingUserId' => 'admin_user-456',
            'grantedAt' => '2025-07-01 10:00:00',
        ]);

        $this->expectException(ServiceException::class);
        PermissionsConsent::fromJsonString($json);
    }

    public function testFromJsonStringWithMissingConsentingUserId(): void
    {
        $json = (string) json_encode([
            'identifier' => 'service-123',
            'revision' => '2025-07-01',
            'grantedAt' => '2025-07-01 10:00:00',
        ]);

        $this->expectException(ServiceException::class);
        PermissionsConsent::fromJsonString($json);
    }

    public function testFromJsonStringWithMissingGrantedAt(): void
    {
        $json = (string) json_encode([
            'identifier' => 'service-123',
            'revision' => '2025-07-01',
            'consentingUserId' => 'admin_user-456',
        ]);

        $this->expectException(ServiceException::class);
        PermissionsConsent::fromJsonString($json);
    }

    public function testJsonSerialize(): void
    {
        $grantedAt = new \DateTime('2025-07-01 10:00:00');
        $consent = new PermissionsConsent(
            'service-123',
            '2025-07-01',
            'admin_user-456',
            $grantedAt
        );

        $serialized = $consent->jsonSerialize();

        static::assertSame('service-123', $serialized['identifier']);
        static::assertSame('2025-07-01', $serialized['revision']);
        static::assertSame('admin_user-456', $serialized['consentingUserId']);
        static::assertSame($grantedAt->format(\DateTimeInterface::ATOM), $serialized['grantedAt']);
    }
}
