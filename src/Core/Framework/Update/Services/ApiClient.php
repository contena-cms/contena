<?php declare(strict_types=1);

namespace Contena\Core\Framework\Update\Services;

use Psr\Clock\ClockInterface;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Struct\Version;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-import-type VersionFixedVulnerabilities from Version
 */
/**
 * @phpstan-import-type VersionFixedVulnerabilities from Version
 */
class ApiClient
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $contenaVersion,
        private readonly string $projectDir,
        private readonly ClockInterface $clock
    ) {
    }

    public function checkForUpdates(): Version
    {
        $fakeVersion = EnvironmentHelper::getVariable('SW_RECOVERY_NEXT_VERSION');
        if (\is_string($fakeVersion)) {
            return new Version([
                'version' => $fakeVersion,
                'title' => 'Contena ' . $fakeVersion,
                'body' => 'This is a fake version for testing purposes',
                'date' => $this->clock->now(),
                'fixedVulnerabilities' => [],
            ]);
        }

        try {
            /** @var array{title: string, body: string, date: string, version: string, fixedVulnerabilities: VersionFixedVulnerabilities[]} $github */
            $github = $this->client->request('GET', 'https://releases.contena.cn/changelog/' . $this->determineLatestContenaVersion() . '.json')->toArray();
        } catch (ClientException $e) {
            if ($e->getCode() === Response::HTTP_NOT_FOUND || $e->getCode() === Response::HTTP_FORBIDDEN) {
                return new Version();
            }

            throw $e;
        }

        $version = new Version();
        $version->title = $github['title'];
        $version->body = $github['body'];
        $version->date = new \DateTimeImmutable($github['date']);
        $version->version = $github['version'];
        $version->fixedVulnerabilities = $github['fixedVulnerabilities'];

        return $version;
    }

    public function downloadRecoveryTool(): void
    {
        if (\is_string(EnvironmentHelper::getVariable('SW_RECOVERY_NEXT_VERSION'))) {
            return;
        }

        $content = $this->client->request('GET', 'https://github.com/contena-cms/web-installer/releases/latest/download/contena-installer.phar.php')->getContent();

        file_put_contents($this->projectDir . '/public/contena-installer.phar.php', $content);
    }

    private function determineLatestContenaVersion(): string
    {
        /** @var non-empty-array<string> $versions */
        $versions = $this->client->request('GET', 'https://releases.contena.cn/changelog/index.json')->toArray();

        usort($versions, static function ($a, $b) {
            return version_compare($b, $a);
        });

        // Index them by major version
        $mappedVersions = [];

        foreach ($versions as $version) {
            if (str_contains($version, 'rc')) {
                continue;
            }

            $major = substr($version, 0, 3);

            if (isset($mappedVersions[$major])) {
                continue;
            }

            $mappedVersions[$major] = $version;
        }

        $currentMajor = substr($this->contenaVersion, 0, 3);
        if (!isset($mappedVersions[$currentMajor])) {
            return strtolower($this->contenaVersion);
        }

        $latestVersion = $mappedVersions[$currentMajor];

        $first = (int) substr($this->contenaVersion, 0, 1);
        $second = (int) substr($this->contenaVersion, 2, 1);
        ++$second;

        if (isset($mappedVersions[$first . '.' . $second])) {
            $latestVersion = $mappedVersions[$first . '.' . $second];
        }

        return $latestVersion;
    }
}
