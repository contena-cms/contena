<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Garan\GaranLabelDurationFormatter;
use Contena\Core\Content\Blog\Garan\GaranLabelInlineImage;
use Contena\Core\Content\Blog\Garan\GaranLabelMailSubscriber;
use Contena\Core\Content\Blog\Garan\GaranLabelRenderer;
use Contena\Core\Content\Blog\Garan\GaranLabelResolver;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(GaranLabelMailSubscriber::class)]
class GaranLabelMailSubscriberTest extends TestCase
{
    private const TEMPLATE = '{% set garanLabel = garanLabels[nestedItem.blogId] ?? null %}';

    public function testSubscribesToMailEvents(): void
    {
        static::assertSame(
            [
                MailBeforeValidateEvent::class => 'addLabels',
                MailBeforeSentEvent::class => 'embedLabelImages',
            ],
            GaranLabelMailSubscriber::getSubscribedEvents()
        );
    }

    public function testAddsTheLabelsOfAllBlogsKeyedByBlogIdWithOneSearch(): void
    {
        $event = $this->createEvent(['blog-a', 'blog-b', null, 'blog-a']);

        $this->createSubscriber([
            static function (Criteria $criteria): BlogCollection {
                static::assertSame(['blog-a', 'blog-b'], $criteria->getIds(), 'each blog once, line items without blog skipped');
                static::assertTrue($criteria->hasAssociation('manufacturer'), 'the label needs the brand');

                return new BlogCollection([
                    self::createBlog('blog-a', guaranteeConfirmed: true),
                    self::createBlog('blog-b', guaranteeConfirmed: true, guaranteeMonths: 30),
                ]);
            },
            static fn () => static::fail('cid and duration have to come from a single blog search'),
        ])->addLabels($event);

        static::assertSame(
            [
                'blog-a' => ['cid' => 'cid:garan-label-nested-36.png', 'duration' => '3', 'termsUrl' => null],
                'blog-b' => ['cid' => 'cid:garan-label-nested-30.png', 'duration' => '2,5', 'termsUrl' => null],
            ],
            $event->getTemplateData()['garanLabels']
        );
    }

    public function testLoadsBlogsWithInheritanceSoVariantsGetTheirParentsLabel(): void
    {
        $event = $this->createEvent(['blog-id']);
        static::assertFalse($event->getContext()->considerInheritance(), 'admin-triggered mails come without inheritance');

        $this->createSubscriber([
            static function (Criteria $criteria, Context $context): BlogCollection {
                static::assertTrue($context->considerInheritance());

                return new BlogCollection([self::createBlog('blog-id', guaranteeConfirmed: true)]);
            },
        ])->addLabels($event);

        static::assertArrayHasKey('blog-id', $event->getTemplateData()['garanLabels']);
        static::assertFalse($event->getContext()->considerInheritance(), 'the mail context is left as it was');
    }

    public function testKeepsTheDurationWithoutPreRenderedImage(): void
    {
        $event = $this->createEvent(['blog-id']);

        $this->createSubscriber([
            new BlogCollection([self::createBlog('blog-id', guaranteeConfirmed: true, guaranteeMonths: 606)]),
        ])->addLabels($event);

        static::assertSame(
            ['blog-id' => ['cid' => null, 'duration' => '50,5', 'termsUrl' => null]],
            $event->getTemplateData()['garanLabels'],
            'Legacy durations above the maximum have no image, the template falls back to the duration text'
        );
    }

    public function testSkipsBlogsWithoutConfirmedGuarantee(): void
    {
        $event = $this->createEvent(['blog-id']);

        $this->createSubscriber([
            new BlogCollection([self::createBlog('blog-id', guaranteeConfirmed: false)]),
        ])->addLabels($event);

        static::assertSame([], $event->getTemplateData()['garanLabels']);
    }

    public function testAddsTheLabelsForThePlainTextTemplate(): void
    {
        $event = new MailBeforeValidateEvent(
            ['contentHtml' => '<p>Order</p>', 'contentPlain' => self::TEMPLATE],
            Context::createDefaultContext(),
            ['order' => self::createOrder(['blog-id'])],
        );

        $this->createSubscriber([
            new BlogCollection([self::createBlog('blog-id', guaranteeConfirmed: true)]),
        ])->addLabels($event);

        static::assertArrayHasKey('blog-id', $event->getTemplateData()['garanLabels']);
    }

    public function testLinksTheTermsUrlOrElseThePublicTermsDocument(): void
    {
        $event = $this->createEvent(['with-url', 'with-document', 'with-private-document']);

        $this->createSubscriber([
            static function (Criteria $criteria): BlogCollection {
                static::assertTrue($criteria->hasAssociation('guaranteeTermsMedia'));

                return new BlogCollection([
                    self::createBlog('with-url', guaranteeConfirmed: true, termsUrl: 'https://example.com/terms', termsMediaUrl: 'https://shop.example.com/terms.pdf'),
                    self::createBlog('with-document', guaranteeConfirmed: true, termsMediaUrl: 'https://shop.example.com/terms.pdf'),
                    self::createBlog('with-private-document', guaranteeConfirmed: true, termsMediaUrl: ''),
                ]);
            },
        ])->addLabels($event);

        static::assertSame(
            [
                'with-url' => 'https://example.com/terms',
                'with-document' => 'https://shop.example.com/terms.pdf',
                'with-private-document' => null,
            ],
            array_map(static fn (array $label): ?string => $label['termsUrl'], $event->getTemplateData()['garanLabels'])
        );
    }

    public function testAttachesTheTermsDocumentsOfLabelledBlogsOnce(): void
    {
        $mailSendConfig = new MailSendSubscriberConfig(false, mediaIds: ['admin-selected-media']);
        $event = new MailBeforeValidateEvent(
            [
                'contentHtml' => self::TEMPLATE,
                'attachmentsConfig' => new MailAttachmentsConfig(Context::createDefaultContext(), new MailTemplateEntity(), $mailSendConfig, [], null),
            ],
            Context::createDefaultContext(),
            ['order' => self::createOrder(['blog-a', 'blog-b', 'unlabelled', 'without-file'])],
        );

        $withoutFile = self::createBlog('without-file', guaranteeConfirmed: true);
        $emptyMedia = new MediaEntity();
        $emptyMedia->setId('empty-media');
        $withoutFile->setGuaranteeTermsMedia($emptyMedia);

        $this->createSubscriber([
            new BlogCollection([
                self::createBlog('blog-a', guaranteeConfirmed: true, termsMediaUrl: 'https://shop.example.com/terms.pdf', termsMediaId: 'terms-media'),
                self::createBlog('blog-b', guaranteeConfirmed: true, termsMediaUrl: 'https://shop.example.com/terms.pdf', termsMediaId: 'terms-media'),
                self::createBlog('unlabelled', guaranteeConfirmed: false, termsMediaUrl: 'https://shop.example.com/other.pdf', termsMediaId: 'other-media'),
                $withoutFile,
            ]),
        ])->addLabels($event);

        static::assertSame(['admin-selected-media', 'terms-media'], $mailSendConfig->getMediaIds());
    }

    /**
     * @return \Generator<string, array{string, array<string, mixed>}>
     */
    public static function mailWithoutLabelsProvider(): \Generator
    {
        yield 'template without labels' => ['<p>{{ order.orderNumber }}</p>', ['order' => self::createOrder(['blog-id'])]];
        yield 'no order entity' => [self::TEMPLATE, ['order' => ['lineItems' => [['blogId' => 'blog-id']]]]];
        yield 'no order' => [self::TEMPLATE, []];
        yield 'no blog line items' => [self::TEMPLATE, ['order' => self::createOrder([null])]];
    }

    /**
     * @param array<string, mixed> $templateData
     */
    #[DataProvider('mailWithoutLabelsProvider')]
    public function testAddsNoLabelsAndDoesNotSearch(string $template, array $templateData): void
    {
        $event = new MailBeforeValidateEvent(['contentHtml' => $template], Context::createDefaultContext(), $templateData);

        $this->createSubscriber([
            static fn () => static::fail('mails that cannot show a label must not load blogs'),
        ])->addLabels($event);

        static::assertArrayNotHasKey('garanLabels', $event->getTemplateData());
    }

    public function testEmbedsReferencedLabelAsInlineImage(): void
    {
        $email = $this->createEmail('<p>Blog</p><img src="cid:garan-label-nested-36.png" alt="GARAN">');

        $this->dispatch($email);

        $attachments = $email->getAttachments();
        static::assertCount(1, $attachments);

        $part = $attachments[0];
        static::assertSame('garan-label-nested-36.png', $part->getName());
        static::assertSame('image/png', $part->getMediaType() . '/' . $part->getMediaSubtype());

        $html = $email->getBody()->bodyToString();
        static::assertStringContainsString('Content-Disposition: inline', $html);
        static::assertStringContainsString('cid:' . $part->getContentId(), $html, 'Symfony Mime has to link the reference to the attached part');
        static::assertStringNotContainsString('cid:garan-label-nested-36.png', $html);
    }

    public function testEmbedsEachLabelOnlyOnce(): void
    {
        $email = $this->createEmail(
            '<img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-30.png">'
        );

        $this->dispatch($email);
        $this->dispatch($email);

        static::assertSame(
            ['garan-label-nested-36.png', 'garan-label-nested-30.png'],
            array_map(static fn (DataPart $part): ?string => $part->getName(), $email->getAttachments())
        );
    }

    public function testIgnoresReferencesWithoutPreRenderedImage(): void
    {
        $email = $this->createEmail('<img src="cid:garan-label-nested-999.png"><img src="cid:logo.png">');

        $this->dispatch($email);

        static::assertSame([], $email->getAttachments());
    }

    public function testIgnoresMailsWithoutHtmlBody(): void
    {
        $email = (new Email())->text('cid:garan-label-nested-36.png');

        $this->dispatch($email);

        static::assertSame([], $email->getAttachments());
    }

    private function createEmail(string $html): Email
    {
        return (new Email())
            ->from('shop@example.com')
            ->to('user@example.com')
            ->subject('Order confirmation')
            ->text('Order confirmation')
            ->html($html);
    }

    private function dispatch(Email $email): void
    {
        $this->createSubscriber([])
            ->embedLabelImages(new MailBeforeSentEvent([], $email, Context::createDefaultContext()));
    }

    /**
     * @param list<string|null> $blogIds
     */
    private function createEvent(array $blogIds): MailBeforeValidateEvent
    {
        return new MailBeforeValidateEvent(
            ['contentHtml' => self::TEMPLATE],
            Context::createDefaultContext(),
            ['order' => self::createOrder($blogIds)],
        );
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createSubscriber(array $searchResults): GaranLabelMailSubscriber
    {
        $resolver = new GaranLabelResolver(
            new GaranLabelDurationFormatter(),
            new GaranLabelRenderer(static::createStub(Environment::class)),
        );

        /** @var StaticEntityRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticEntityRepository($searchResults, new BlogDefinition());

        return new GaranLabelMailSubscriber(new GaranLabelInlineImage(), $blogRepository, $resolver);
    }

    /**
     * @param list<string|null> $blogIds
     */
    private static function createOrder(array $blogIds): OrderEntity
    {
        $lineItems = new OrderLineItemCollection();

        foreach ($blogIds as $index => $blogId) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId('line-item-' . $index);
            $lineItem->setBlogId($blogId);
            $lineItems->add($lineItem);
        }

        $order = new OrderEntity();
        $order->setLineItems($lineItems);

        return $order;
    }

    private static function createBlog(
        string $id,
        bool $guaranteeConfirmed,
        int $guaranteeMonths = 36,
        ?string $termsUrl = null,
        ?string $termsMediaUrl = null,
        string $termsMediaId = 'terms-media-id',
    ): BlogEntity {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setName('ACME');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $blog = new BlogEntity();
        $blog->setId($id);
        $blog->setManufacturer($manufacturer);
        $blog->setManufacturerNumber('ACME-123');
        $blog->setGuaranteeMonths($guaranteeMonths);
        $blog->setGuaranteeConfirmed($guaranteeConfirmed);
        $blog->setGuaranteeTermsUrl($termsUrl);

        if ($termsMediaUrl !== null) {
            $media = new MediaEntity();
            $media->setId($termsMediaId);
            $media->setUrl($termsMediaUrl);
            $media->setPath('media/terms.pdf');

            $blog->setGuaranteeTermsMediaId($termsMediaId);
            $blog->setGuaranteeTermsMedia($media);
        }

        return $blog;
    }
}
