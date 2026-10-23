<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BlogManufacturerGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly BlogManufacturerDefinition $blogManufacturerDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return BlogManufacturerDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $payload[] = [
                'id' => Uuid::randomHex(),
                'name' => $context->getFaker()->format('company'),
                'link' => $context->getFaker()->format('url'),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];
        }

        $writeContext = WriteContext::createFromContext($context->getContext());

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->writer->upsert($this->blogManufacturerDefinition, $chunk, $writeContext);
            $context->getConsole()->progressAdvance(\count($chunk));
        }

        $context->getConsole()->progressFinish();
    }
}
