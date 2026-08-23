<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Tenant\TenantEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The tenant code is part of the tenant's addressing (subdomain convention,
 * path convention); changing it would hijack or orphan the tenant's URLs.
 *
 * @internal
 */
final class TenantCodeImmutableSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PostWriteValidationEvent::class => 'validate'];
    }

    public function validate(PostWriteValidationEvent $event): void
    {
        $commands = $event->getCommandsForEntity(TenantEntity::class);

        foreach ($commands as $command) {
            if (!$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('code', $payload)) {
                continue;
            }

            $current = $this->connection->fetchOne(
                'SELECT `code` FROM `tenant` WHERE `id` = :id',
                ['id' => $command->getPrimaryKey()['id']],
            );

            if ($current === $payload['code']) {
                continue;
            }

            $event->getExceptions()->add(new WriteConstraintViolationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'The tenant code is part of the tenant addressing and can not be changed.',
                    'The tenant code is part of the tenant addressing and can not be changed.',
                    [],
                    null,
                    '/code',
                    $payload['code'],
                ),
            ]), '/'));
        }
    }
}
