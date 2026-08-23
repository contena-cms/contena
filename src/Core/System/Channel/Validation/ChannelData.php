<?php

declare(strict_types=1);

namespace Contena\Core\System\Channel\Validation;

/**
 * @internal
 */
class ChannelData
{
    public ?string $currentDefault = null;

    public ?string $newDefault = null;

    public ?string $updateId = null;

    /**
     * @var list<string>
     */
    public array $state = [];

    /**
     * @var list<string>|null
     */
    public ?array $inserts = null;

    /**
     * @var list<string>
     */
    public array $deletions = [];
}
