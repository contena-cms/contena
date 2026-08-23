<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Exception\DuplicateBlogSearchConfigFieldException;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BlogSearchConfigFieldExceptionHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDuplicateInsert(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog_search_config');

        $ids = new IdsCollection();
        $config = [
            'id' => $ids->get('config'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'andLogic' => true,
            'minSearchLength' => 3,
            'configFields' => [
                ['id' => $ids->get('field-1'), 'field' => 'name'],
                ['id' => $ids->get('field-2'), 'field' => 'name'],
            ],
        ];

        $this->expectExceptionObject(new DuplicateBlogSearchConfigFieldException('name', new \Exception()));

        static::getContainer()->get('blog_search_config.repository')
            ->create([$config], Context::createDefaultContext());
    }
}
