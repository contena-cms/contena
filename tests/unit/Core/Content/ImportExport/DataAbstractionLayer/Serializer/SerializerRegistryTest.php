<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\ImportExportException;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(SerializerRegistry::class)]
class SerializerRegistryTest extends TestCase
{
    public function testGetEntityThrowsExceptionBecauseNoSerializerFound(): void
    {
        $registry = new SerializerRegistry([], []);

        $this->expectExceptionObject(ImportExportException::serializerNotFound('test'));
        $registry->getEntity('test');
    }

    public function testGetFieldSerializerThrowsExceptionBecauseNoSerializerFound(): void
    {
        $registry = new SerializerRegistry([], []);

        $field = new BoolField('test', 'test');
        $this->expectExceptionObject(ImportExportException::serializerNotFound($field::class));
        $registry->getFieldSerializer($field);
    }
}
