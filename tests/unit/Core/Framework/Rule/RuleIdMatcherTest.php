<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Contena\Core\Framework\DataAbstractionLayer\Contract\RuleIdAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\RuleIdMatcher;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RuleIdMatcher::class)]
class RuleIdMatcherTest extends TestCase
{
    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testFilter(): void
    {
        $option1 = $this->createOption($this->ids->get('option1'), $this->ids->get('rule1'));
        $option2 = $this->createOption($this->ids->get('option2'), $this->ids->get('rule2'));
        $option3 = $this->createOption($this->ids->get('option3'), $this->ids->get('rule3'));

        $options = [$option1, $option2, $option3];

        $matcher = new RuleIdMatcher();

        $filtered = $matcher->filter($options, [$this->ids->get('rule2'), $this->ids->get('rule3')]);

        static::assertCount(2, $filtered);
        static::assertSame($this->ids->get('option2'), $filtered[0]->getId());
        static::assertSame($this->ids->get('option3'), $filtered[1]->getId());
    }

    public function testFilterWithNullAvailabilityRuleId(): void
    {
        $option1 = $this->createOption($this->ids->get('option1'), $this->ids->get('rule1'));
        $option2 = $this->createOption($this->ids->get('option2'));
        $option3 = $this->createOption($this->ids->get('option3'), $this->ids->get('rule3'));

        $options = [$option1, $option2, $option3];

        $matcher = new RuleIdMatcher();

        $filtered = $matcher->filter($options, [$this->ids->get('rule2'), $this->ids->get('rule3')]);

        static::assertCount(2, $filtered);
        static::assertSame($this->ids->get('option2'), $filtered[0]->getId());
        static::assertSame($this->ids->get('option3'), $filtered[1]->getId());
    }

    public function testFilterCollection(): void
    {
        $option1 = $this->createOption($this->ids->get('option1'), $this->ids->get('rule1'));
        $option2 = $this->createOption($this->ids->get('option2'), $this->ids->get('rule2'));
        $option3 = $this->createOption($this->ids->get('option3'), $this->ids->get('rule3'));

        $options = new class([$option1, $option2, $option3]) extends Collection {
        };

        $matcher = new RuleIdMatcher();

        /** @var Collection<IdAware&RuleIdAware> $filtered */
        $filtered = $matcher->filterCollection($options, [$this->ids->get('rule2'), $this->ids->get('rule3')]);

        static::assertCount(2, $filtered);
        static::assertSame($this->ids->get('option2'), $filtered->first()?->getId());
        static::assertSame($this->ids->get('option3'), $filtered->last()?->getId());
    }

    public function testFilterCollectionWithNullAvailabilityRuleId(): void
    {
        $option1 = $this->createOption($this->ids->get('option1'), $this->ids->get('rule1'));
        $option2 = $this->createOption($this->ids->get('option2'));
        $option3 = $this->createOption($this->ids->get('option3'), $this->ids->get('rule3'));

        $options = new class([$option1, $option2, $option3]) extends Collection {
        };

        $matcher = new RuleIdMatcher();

        /** @var Collection<IdAware&RuleIdAware> $filtered */
        $filtered = $matcher->filterCollection($options, [$this->ids->get('rule2'), $this->ids->get('rule3')]);

        static::assertCount(2, $filtered);
        static::assertSame($this->ids->get('option2'), $filtered->first()?->getId());
        static::assertSame($this->ids->get('option3'), $filtered->last()?->getId());
    }

    /**
     * @return (IdAware&RuleIdAware)
     */
    private function createOption(?string $id = null, ?string $ruleId = null): object
    {
        $id ??= Uuid::randomHex();

        return new class($id, $ruleId) implements IdAware, RuleIdAware {
            public function __construct(
                private readonly string $id,
                private readonly ?string $ruleId = null,
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getAvailabilityRuleId(): ?string
            {
                return $this->ruleId;
            }
        };
    }
}
