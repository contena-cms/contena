<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserBuilder;
use Contena\Core\Content\Blog\Subscriber\BlogDescriptionTeaserSubscriber;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[CoversClass(BlogDescriptionTeaserSubscriber::class)]
class BlogDescriptionTeaserSubscriberTest extends TestCase
{
    public function testSubscribesToWriteEvent(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'beforeWrite'],
            BlogDescriptionTeaserSubscriber::getSubscribedEvents()
        );
    }

    public function testStripsHtmlAndKeepsText(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->expects($this->once())->method('hasField')->with('description')->willReturn(true);
        $command->method('getPayload')->willReturn([
            'description' => '<p style="color: red;">Hello <strong>World</strong></p>',
        ]);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('description_teaser', 'Hello World');

        $this->dispatch($command);
    }

    public function testTruncatesToMaxLength(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->expects($this->once())->method('hasField')->with('description')->willReturn(true);
        $command->method('getPayload')->willReturn([
            'description' => str_repeat('a', 1000),
        ]);
        $command->expects($this->once())
            ->method('addPayload')
            ->willReturnCallback(static function (string $field, ?string $value): void {
                static::assertSame('description_teaser', $field);
                static::assertNotNull($value);
                static::assertSame(512, mb_strlen($value));
            });

        $this->dispatch($command);
    }

    public function testKeepsNullDescription(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->expects($this->once())->method('hasField')->with('description')->willReturn(true);
        $command->method('getPayload')->willReturn(['description' => null]);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('description_teaser', null);

        $this->dispatch($command);
    }

    public function testIgnoresCommandsWithoutDescription(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->expects($this->once())->method('hasField')->with('description')->willReturn(false);
        $command->expects($this->never())->method('addPayload');

        $this->dispatch($command);
    }

    private function dispatch(WriteCommand $command): void
    {
        $builder = new BlogDescriptionTeaserBuilder(
            new HtmlSanitizer(null, false, [], [BlogDescriptionTeaserBuilder::TEASER_FIELD => ['sets' => []]])
        );

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(BlogTranslationDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        (new BlogDescriptionTeaserSubscriber($builder))->beforeWrite($event);
    }
}
