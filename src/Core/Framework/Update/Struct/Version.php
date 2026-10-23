<?php declare(strict_types=1);

namespace Contena\Core\Framework\Update\Struct;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Clock\Clock;

/**
 * @phpstan-type VersionFixedVulnerabilities array{severity: string, summary: string, link: string}
 */
class Version extends Struct
{
    public string $title = '';

    public string $body = '';

    public \DateTimeImmutable $date;

    public string $version = '';

    /**
     * @var VersionFixedVulnerabilities[]
     */
    public array $fixedVulnerabilities = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->date = Clock::get()->now();

        if (isset($data['date']) && \is_string($data['date'])) {
            $data['date'] = new \DateTimeImmutable($data['date']);
        }

        $this->assign($data);
    }

    public function getApiAlias(): string
    {
        return 'update_api_version';
    }
}
