<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Contena\Core\System\User\UserDefinition;

class UpdatedByField extends FkField
{
    /**
     * @var array<string>
     */
    private readonly array $allowedWriteScopes;

    /**
     * @param list<string> $allowedWriteScopes
     */
    public function __construct(array $allowedWriteScopes = [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE])
    {
        parent::__construct('updated_by_id', 'updatedById', UserDefinition::class);

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
