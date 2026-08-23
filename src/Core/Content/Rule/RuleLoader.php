<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @final
 */
class RuleLoader extends AbstractRuleLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<RuleCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context): RuleCollection
    {
        $criteria = new Criteria()
            ->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('id'))
            ->addFilter(new EqualsFilter('invalid', false))
            ->setLimit(500)
            ->setTitle('rule-loader::load-rules');

        $iterator = new RepositoryIterator($this->repository, $context, $criteria);
        $rules = new RuleCollection();
        while (($result = $iterator->fetch()) !== null) {
            foreach ($result->getEntities() as $rule) {
                if ($rule->getPayload() !== null) {
                    $rules->add($rule);
                }
            }

            if ($result->getEntities()->count() < 500) {
                break;
            }
        }

        return $rules;
    }
}
