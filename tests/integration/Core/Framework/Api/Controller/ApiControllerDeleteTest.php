<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerDeleteTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testDelete(): void
    {
        $id = Uuid::randomHex();
        $this->getBrowser()->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Blog']);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'blog', $id);

        $this->getBrowser()->jsonRequest('DELETE', '/api/blog/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
        $this->assertEntityNotExists($this->getBrowser(), 'blog', $id);
    }

    public function testDeleteWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Blog']);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['blog:read', 'blog:create']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('DELETE', '/api/blog/' . $id);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        $this->assertEntityExists($browser, 'blog', $id);
    }
}
