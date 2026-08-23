<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\ScheduledTask\UpdateTranslationsTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[CoversClass(UpdateTranslationsTask::class)]
class UpdateTranslationsTaskTest extends TestCase
{
    public function testTask(): void
    {
        static::assertSame('translation.update', UpdateTranslationsTask::getTaskName());
        static::assertSame(86400, UpdateTranslationsTask::getDefaultInterval());
        static::assertTrue(UpdateTranslationsTask::shouldRescheduleOnFailure());
    }

    public function testTaskDoesNotRunWhenDisabled(): void
    {
        static::assertFalse(UpdateTranslationsTask::shouldRun(new ParameterBag([
            'contena.translation.scheduled_task.enabled' => false,
        ])));
    }
}
