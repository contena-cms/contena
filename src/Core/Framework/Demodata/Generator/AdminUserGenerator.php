<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\Language\LanguageCollection;

/**
 * @internal
 */
class AdminUserGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly AdminUserDefinition $admin_userDefinition,
        private readonly EntityRepository $languageRepository
    ) {
    }

    public function getDefinition(): string
    {
        return AdminUserDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();
            $firstName = $context->getFaker()->firstName();
            $lastName = $context->getFaker()->format('lastName');
            $title = $this->getRandomTitle();

            $admin_user = [
                'id' => $id,
                'title' => $title,
                'name' => $firstName,
                'lastName' => $lastName,
                'username' => $context->getFaker()->format('admin_userName'),
                'email' => $id . $context->getFaker()->format('safeEmail'),
                'password' => 'contena',
                'localeId' => $this->getLocaleId($context->getContext()),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            $payload[] = $admin_user;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->admin_userDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->admin_userDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomTitle(): string
    {
        $titles = ['', 'Dr.', 'Dr. med.', 'Prof.', 'Prof. Dr.'];

        return $titles[array_rand($titles)];
    }

    private function getLocaleId(Context $context): string
    {
        $first = $this->languageRepository->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), $context)->getEntities()->first();
        \assert($first !== null);

        return $first->getLocaleId();
    }
}
