<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaEntity;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MailAttachmentsBuilder::class)]
class MailAttachmentsBuilderTest extends TestCase
{
    private MediaService&Stub $mediaService;

    /**
     * @var EntityRepository<MediaCollection>&Stub
     */
    private EntityRepository&Stub $mediaRepository;

    protected function setUp(): void
    {
        $this->mediaService = static::createStub(MediaService::class);
        $this->mediaRepository = static::createStub(EntityRepository::class);
    }

    public function testBuildTemplateMediaAttachments(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $extension = new MailSendSubscriberConfig(false);

        $mediaA = new MailTemplateMediaEntity();
        $mediaA->setId(Uuid::randomHex());
        $mediaA->setMedia(new MediaEntity());
        $mediaA->setLanguageId($context->getLanguageId());
        $mediaB = new MailTemplateMediaEntity();
        $mediaB->setId(Uuid::randomHex());
        $mediaC = new MailTemplateMediaEntity();
        $mediaC->setId(Uuid::randomHex());
        $mediaC->setMedia(new MediaEntity());
        $mediaC->setLanguageId($context->getLanguageId());

        $mailTemplate->setMedia(new MailTemplateMediaCollection([$mediaA, $mediaB, $mediaC]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->exactly(2))
            ->method('getAttachment')
            ->willReturnOnConsecutiveCalls(
                [
                    'content' => 'foo',
                    'fileName' => 'foo',
                    'mimeType' => 'foo',
                ],
                [
                    'content' => 'bar',
                    'fileName' => 'bar',
                    'mimeType' => 'bar',
                ]
            );
        $this->mediaService = $mediaService;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension);

        static::assertSame(
            [
                [
                    'content' => 'foo',
                    'fileName' => 'foo',
                    'mimeType' => 'foo',
                ],
                [
                    'content' => 'bar',
                    'fileName' => 'bar',
                    'mimeType' => 'bar',
                ],
            ],
            $attachments
        );
    }

    private function createBuilder(): MailAttachmentsBuilder
    {
        return new MailAttachmentsBuilder(
            $this->mediaService,
            $this->mediaRepository,
        );
    }
}
