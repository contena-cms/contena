<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataException;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class NewsletterRecipientGenerator implements DemodataGeneratorInterface
{
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly NewsletterRecipientDefinition $newsletterRecipientDefinition,
        private readonly Connection $connection,
    ) {
    }

    public function getDefinition(): string
    {
        return NewsletterRecipientDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $channelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');
        if ($channelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $writeContext = WriteContext::createFromContext($context->getContext());
        $faker = $context->getFaker();

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();

            $newsletterRecipient = [
                'id' => $id,
                'email' => $id . $faker->format('safeEmail'),
                'firstName' => $faker->format('firstName'),
                'lastName' => $faker->format('lastName'),
                'hash' => Uuid::randomHex(),
                'channelId' => $channelIds[array_rand($channelIds)],
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            $payload[] = $newsletterRecipient;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->newsletterRecipientDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->newsletterRecipientDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }
}
