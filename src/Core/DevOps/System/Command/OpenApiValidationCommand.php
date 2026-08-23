<?php declare(strict_types=1);

namespace Contena\Core\DevOps\System\Command;

use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'open-api:validate',
    description: 'Validates the OpenAPI schema',
)]
class OpenApiValidationCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly DefinitionService $definitionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('validatorUrl', InputArgument::OPTIONAL, 'The URL of the validator', 'https://validator.swagger.io/validator/debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $validatorURL = $input->getArgument('validatorUrl');
        $schema = $this->definitionService->generate(
            OpenApi3Generator::FORMAT,
            DefinitionService::API,
        );

        $response = $this->client->request('POST', $validatorURL, [
            'json' => $schema,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        $content = $response->toArray();

        // The CI validator returns an empty response if the schema is valid
        // The public Web validator returns an object with an empty (schemaValidation)Messages array
        $messages = array_merge(
            $content['messages'] ?? [],
            $content['schemaValidationMessages'] ?? []
        );

        if ($messages === []) {
            return Command::SUCCESS;
        }

        $style = new ContenaStyle($input, $output);
        $this->renderErrorMessages($style, $messages);

        return Command::FAILURE;
    }

    /**
     * @param array<string, string|array<mixed>> $messages
     */
    private function renderErrorMessages(ContenaStyle $style, array $messages): void
    {
        $style->error('The OpenAPI schema is invalid:');
        $table = $style->createTable();
        $table->setHeaders(['No.', 'Error']);

        foreach ($messages as $i => $message) {
            if (\is_array($message)) {
                $message = json_encode($message, \JSON_PRETTY_PRINT);
            }
            $table->addRow([$i, $message]);
        }

        $table->render();
    }
}
