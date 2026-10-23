<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;

/**
 * @internal
 */
class StubContextGatewayCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'test';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data = [],
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
