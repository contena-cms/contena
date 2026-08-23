<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class RemoteAddressFieldSerializer extends AbstractFieldSerializer
{
    protected const string CONFIG_KEY = 'core.loginRegistration.customerIpAddressesNotAnonymously';

    /**
     * @internal
     */
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly SystemConfigService $configService
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof RemoteAddressField) {
            throw DataAbstractionLayerException::invalidSerializerField(RemoteAddressField::class, $field);
        }

        if (!$data->getValue()) {
            return;
        }

        if ($this->configService->get(self::CONFIG_KEY)) {
            yield $field->getStorageName() => $data->getValue();

            return;
        }

        yield $field->getStorageName() => IpUtils::anonymize($data->getValue());
    }

    public function decode(Field $field, mixed $value): ?string
    {
        return $value;
    }
}
