<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\File\ChannelFileRequestPathResolver;

/**
 * @internal
 */
#[CoversClass(ChannelFileRequestPathResolver::class)]
class ChannelFileRequestPathResolverTest extends TestCase
{
    public function testItBuildsTemplatePathForNestedPublicFile(): void
    {
        $templatePath = new ChannelFileRequestPathResolver()->buildTemplatePath('agentic', '.well-known/ucp.json');

        static::assertSame('files/agentic/.well-known/ucp.json.twig', $templatePath);
    }

    public function testItRejectsFileFamilyLongerThanDatabaseColumn(): void
    {
        $fileFamily = str_repeat('a', 65);

        $this->expectExceptionObject(ChannelException::invalidChannelFileFamily($fileFamily));

        new ChannelFileRequestPathResolver()->validateFileFamily($fileFamily);
    }
}
