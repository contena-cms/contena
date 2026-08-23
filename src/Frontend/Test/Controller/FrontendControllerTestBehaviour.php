<?php declare(strict_types=1);

namespace Contena\Frontend\Test\Controller;

use Doctrine\DBAL\Connection;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;

trait FrontendControllerTestBehaviour
{
    private ?TestBrowser $frontendBrowser = null;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function request(string $method, string $path, array $data, array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Response
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $this->frontendBrowser = $browser;
        $browser->request($method, EnvironmentHelper::getVariable('APP_URL') . '/' . $path, $data, $files, $server, $content, $changeHistory);

        return $browser->getResponse();
    }

    /**
     * Returns the container of the browser that performed the last request().
     */
    public function getFrontendRequestContainer(): ContainerInterface
    {
        if ($this->frontendBrowser === null) {
            throw new \LogicException('The frontend request container can only be requested after calling `request`.');
        }

        return $this->frontendBrowser->getContainer();
    }

    public function getChannelId(): string
    {
        return (string) static::getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT LOWER(HEX(channel_id)) FROM channel_domain WHERE url = :url',
                ['url' => EnvironmentHelper::getVariable('APP_URL')]
            );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function tokenize(string $route, array $data): array
    {
        $requestStack = new RequestStack();
        $request = new Request();
        /** @var Session $session */
        $session = $this->getSession();
        $request->setSession($session);
        $requestStack->push($request);

        return $data;
    }

    abstract protected static function getKernel(): KernelInterface;

    abstract protected static function getContainer(): ContainerInterface;
}
