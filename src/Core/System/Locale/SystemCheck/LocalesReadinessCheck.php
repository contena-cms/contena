<?php declare(strict_types=1);

namespace Contena\Core\System\Locale\SystemCheck;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Locale\Util\LocaleHelper;

/**
 * @internal
 */
#[Package('discovery')]
class LocalesReadinessCheck extends BaseCheck
{
    /**
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(private readonly EntityRepository $localeRepository)
    {
    }

    public function run(): Result
    {
        $locales = $this->localeRepository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities()
            ->map(static fn (LocaleEntity $locale) => $locale->getCode());

        $invalidLocales = array_filter(
            $locales,
            static fn (string $locale) => !LocaleHelper::isLocale($locale)
        );

        $status = $invalidLocales === [] ? Status::OK : Status::WARNING;

        return new Result(
            $this->name(),
            $status,
            $status === Status::OK ? 'All locales are OK' : 'Some locales are invalid',
            $status === Status::OK,
            $invalidLocales
        );
    }

    public function category(): Category
    {
        return Category::SYSTEM;
    }

    public function name(): string
    {
        return 'LocalesReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::longRunning();
    }
}
