<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;

/**
 * @internal
 */
#[CoversClass(ApiAware::class)]
class ApiAwareTest extends TestCase
{
    public function testDefaultAllowsBothApis(): void
    {
        $flag = new ApiAware();

        static::assertTrue($flag->isBaseUrlAllowed('/api'));
        static::assertTrue($flag->isBaseUrlAllowed('/channel-api'));

        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(ChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testOnlyAdminApiAware(): void
    {
        $flag = new ApiAware(AdminApiSource::class);

        static::assertTrue($flag->isBaseUrlAllowed('/api'));
        static::assertFalse($flag->isBaseUrlAllowed('/channel-api'));

        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertFalse($flag->isSourceAllowed(ChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testOnlyChannelApiAware(): void
    {
        $flag = new ApiAware(ChannelApiSource::class);

        static::assertFalse($flag->isBaseUrlAllowed('/api'));
        static::assertTrue($flag->isBaseUrlAllowed('/channel-api'));

        static::assertFalse($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(ChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }
}
