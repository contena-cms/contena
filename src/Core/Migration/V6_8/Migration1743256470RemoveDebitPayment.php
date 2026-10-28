<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @no-indexer-required: false positive. 'payment_method.indexer' is per row, not cross-row.
 */
#[Package('checkout')]
class Migration1743256470RemoveDebitPayment extends MigrationStep
{
    public const METHOD_HANDLER = 'Contena\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\DebitPayment';

    public function getCreationTimestamp(): int
    {
        return 1743256470;
    }

    public function update(Connection $connection): void
    {
        $ids = $connection->fetchFirstColumn(
            'SELECT id FROM payment_method WHERE handler_identifier = :handler',
            ['handler' => self::METHOD_HANDLER]
        );

        foreach ($ids as $id) {
            try {
                $connection->delete('payment_method', ['id' => $id]);
            } catch (Exception) {
                $connection->update(
                    'payment_method',
                    [
                        'handler_identifier' => DefaultPayment::class,
                        'active' => 0,
                    ],
                    ['id' => $id],
                );
            }
        }
    }
}
