<?php declare(strict_types=1);

namespace Contena\Core\Installer\Requirements\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Installer\InstallerException;

/**
 * @internal
 */
abstract class RequirementCheck extends Struct
{
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_ERROR = 'error';
    public const string STATUS_WARNING = 'warning';

    private const array ALLOWED_STATUS = [self::STATUS_SUCCESS, self::STATUS_ERROR, self::STATUS_WARNING];

    private readonly string $name;

    private readonly string $status;

    public function __construct(
        string $name,
        string $status
    ) {
        if ($name === '') {
            throw InstallerException::invalidRequirementCheck('Empty name for RequirementCheck provided.');
        }

        if (!\in_array($status, self::ALLOWED_STATUS, true)) {
            throw InstallerException::invalidRequirementCheck(\sprintf(
                'Invalid status for RequirementCheck, got "%s", allowed values are "%s".',
                $status,
                implode('", "', self::ALLOWED_STATUS)
            ));
        }

        $this->name = $name;
        $this->status = $status;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
