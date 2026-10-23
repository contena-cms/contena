<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Contena\Core\Framework\Deprecation\BCChange\ParameterDefaultValueChange;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserDefinition;

class UpdatedByField extends FkField
{
    /**
     * @var array<string>
     */
    private readonly array $allowedWriteScopes;

    /**
     * @param list<string> $allowedWriteScopes
     */
    #[ParameterDefaultValueChange(version: 'v6.8.0', parameterName: 'allowedWriteScopes', newDefaultValue: [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE])]
    public function __construct(array $allowedWriteScopes = [Context::SYSTEM_SCOPE])
    {
        parent::__construct('updated_by_id', 'updatedById', AdminUserDefinition::class);

        if (\func_num_args() === 0 && Feature::isActive('v6.8.0.0')) {
            $allowedWriteScopes = [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE];
        }

        if (\func_num_args() === 0 && !Feature::isActive('v6.8.0.0') && !EnvironmentHelper::getVariable('TESTS_RUNNING')) {
            trigger_deprecation(
                'contena/core',
                '',
                \sprintf(
                    'Not passing $allowedWriteScopes to %s::__construct() will include Context::CRUD_API_SCOPE by default in v6.8.0. Pass the desired scopes explicitly to keep the current behavior.',
                    self::class
                )
            );
        }

        $this->allowedWriteScopes = $allowedWriteScopes;
    }

    /**
     * @return list<string>
     */
    public function getAllowedWriteScopes(): array
    {
        return $this->allowedWriteScopes;
    }

    protected function getSerializerClass(): string
    {
        return UpdatedByFieldSerializer::class;
    }
}
