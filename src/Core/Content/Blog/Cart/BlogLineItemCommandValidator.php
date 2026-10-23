<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Contena\Core\Content\Blog\Exception\BlogLineItemDifferentIdException;
use Contena\Core\Content\Blog\Exception\BlogLineItemInconsistentException;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\SetNullOnDeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Content\Blog\Cart\BlogLineItemCommandValidatorTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see BlogLineItemCommandValidatorTest
 */
class BlogLineItemCommandValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $commands = $event->getCommandsForEntity(OrderLineItemDefinition::ENTITY_NAME);
        $blogs = $this->findBlogs($commands);

        foreach ($commands as $command) {
            if ($command instanceof SetNullOnDeleteCommand) {
                continue;
            }

            $payload = $command->getPayload();

            $lineItemId = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);

            $blogIdChanged = \array_key_exists('blog_id', $payload);

            $referenceIdChanged = \array_key_exists('referenced_id', $payload);

            $lineItemPayload = isset($payload['payload']) ? json_decode((string) $payload['payload'], true, 512, \JSON_THROW_ON_ERROR) : [];
            $orderNumberChanged = \array_key_exists('blogNumber', $lineItemPayload);

            if (!$this->isBlog($blogs, $payload, $lineItemId)) {
                continue;
            }

            $somethingChanged = $blogIdChanged || $referenceIdChanged || $orderNumberChanged;

            $allChanged = $blogIdChanged && $referenceIdChanged && $orderNumberChanged;

            // has a field changed?
            if (!$somethingChanged) {
                continue;
            }

            $blogId = isset($payload['blog_id']) ? Uuid::fromBytesToHex($payload['blog_id']) : null;
            $referenceId = $payload['referenced_id'] ?? null;

            if ($blogId !== $referenceId) {
                $event->getExceptions()->add(
                    new BlogLineItemDifferentIdException($lineItemId)
                );
            }

            // all fields updated? everything is consistent
            if ($allChanged) {
                continue;
            }

            $event->getExceptions()->add(
                new BlogLineItemInconsistentException($lineItemId)
            );
        }
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, int>
     */
    private function findBlogs(array $commands): array
    {
        $ids = array_map(static function (WriteCommand $command) {
            if ($command instanceof UpdateCommand) {
                return $command->getPrimaryKey()['id'];
            }

            return null;
        }, $commands);

        $ids = array_values(array_filter($ids));

        if ($ids === []) {
            return [];
        }

        /** @var array<string, int<0, max>> $blogs */
        $blogs = \array_flip($this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) FROM order_line_item WHERE id IN (:ids) AND type = \'blog\'',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        ));

        return $blogs;
    }

    /**
     * @param array<string, mixed> $blogs
     * @param array<string, mixed> $payload
     */
    private function isBlog(array $blogs, array $payload, string $lineItemId): bool
    {
        if (isset($payload['type'])) {
            return $payload['type'] === LineItem::BLOG_LINE_ITEM_TYPE;
        }

        return isset($blogs[$lineItemId]);
    }
}
