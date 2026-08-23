<?php declare(strict_types=1);

namespace Contena\Core\Test;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class FixtureLoader
{
    private readonly Connection $connection;

    private readonly EntityWriterInterface $writer;

    public function __construct(
        private readonly ContainerInterface $container
    ) {
        $this->connection = $container->get(Connection::class);
        $this->writer = $container->get(EntityWriter::class);
    }

    public function load(string $content, ?IdsCollection $ids = null): IdsCollection
    {
        if (!$ids) {
            $ids = new IdsCollection([
                'language' => Defaults::LANGUAGE_SYSTEM,
                'locale' => $this->getLocaleIdOfSystemLanguage(),
                'es-locale' => $this->getLocaleIdFromLocaleCode('es-ES'),
            ]);
        }

        $content = $this->replaceIds($ids, $content);
        $this->sync(\json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        $this->container->get(EntityIndexerRegistry::class)->index(false);

        return $ids;
    }

    private function replaceIds(IdsCollection $ids, string $content): string
    {
        return (string) \preg_replace_callback('/"{.*}"/mU', static function (array $match) use ($ids) {
            $key = \str_replace(['"{', '}"'], '', $match[0]);

            return '"' . $ids->create($key) . '"';
        }, $content);
    }

    /**
     * @param array<array<int, mixed>> $content
     */
    private function sync(array $content): void
    {
        $operations = [];
        foreach ($content as $entity => $data) {
            $operations[] = new SyncOperation($entity, $entity, 'upsert', $data);
        }

        $this->writer->sync($operations, WriteContext::createFromContext(Context::createDefaultContext()));
    }

    private function getLocaleIdOfSystemLanguage(): string
    {
        return $this->connection
            ->fetchOne(
                'SELECT LOWER(HEX(locale_id)) FROM language WHERE id = UNHEX(:systemLanguageId)',
                ['systemLanguageId' => Defaults::LANGUAGE_SYSTEM]
            );
    }

    private function getLocaleIdFromLocaleCode(string $code): string
    {
        return $this->connection
            ->fetchOne(
                'SELECT LOWER(HEX(id)) from locale WHERE code = :code',
                ['code' => $code]
            );
    }
}
