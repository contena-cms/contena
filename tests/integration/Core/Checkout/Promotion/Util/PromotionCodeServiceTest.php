<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Promotion\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Promotion\PromotionEntity;
use Contena\Core\Checkout\Promotion\PromotionException;
use Contena\Core\Checkout\Promotion\Util\PromotionCodeService;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionCodeServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private PromotionCodeService $codesService;

    protected function setUp(): void
    {
        $this->codesService = static::getContainer()->get(PromotionCodeService::class);
    }

    public function testGetFixedCode(): void
    {
        $code = $this->codesService->getFixedCode();

        static::assertSame(8, \strlen($code));
        static::assertMatchesRegularExpression('/([A-Z]\d){4}/', $code);
    }

    #[DataProvider('codePreviewDataProvider')]
    public function testGetCodePreview(string $codePattern, string $expectedRegex): void
    {
        $actualCode = $this->codesService->getPreview($codePattern);

        static::assertMatchesRegularExpression($expectedRegex, $actualCode);
    }

    /**
     * @return iterable<array<string>>
     */
    public static function codePreviewDataProvider(): iterable
    {
        yield 'single letter placeholder expands to one uppercase character' => ['%s', '/([A-Z]){1}/'];
        yield 'single digit placeholder expands to one digit' => ['%d', '/(\d){1}/'];
        yield 'three letter placeholders expand to three uppercase characters' => ['%s%s%s', '/([A-Z]){3}/'];
        yield 'three digit placeholders expand to three digits' => ['%d%d%d', '/(\d){3}/'];
        yield 'mixed letter digit letter placeholders expand in order' => ['%s%d%s', '/([A-Z]\d[A-Z])/'];
        yield 'mixed digit letter digit placeholders expand in order' => ['%d%s%d', '/(\d[A-Z]\d)/'];
        yield 'prefixed letter and digit placeholders preserve the prefix' => ['PREFIX_%s%s%d%d', '/PREFIX_([A-Z]){2}(\d){2}/'];
        yield 'suffixed digit and letter placeholders preserve the suffix' => ['%d%d%s%s_SUFFIX', '/(\d){2}([A-Z]){2}_SUFFIX/'];
        yield 'prefixed and suffixed letter placeholders preserve static text' => ['PREFIX_%s%s_SUFFIX', '/PREFIX_([A-Z]){2}_SUFFIX/'];
        yield 'prefixed and suffixed digit placeholders preserve static text' => ['PREFIX_%d%d_SUFFIX', '/PREFIX_(\d){2}_SUFFIX/'];
        yield 'prefixed and suffixed letter digit placeholders preserve static text' => ['PREFIX_%s%d_SUFFIX', '/PREFIX_([A-Z]\d)_SUFFIX/'];
        yield 'prefixed and suffixed digit letter placeholders preserve static text' => ['PREFIX_%d%s_SUFFIX', '/PREFIX_(\d[A-Z])_SUFFIX/'];
        yield 'unrendered placeholders after static text remain unchanged' => ['PREFIX_%d%s_NOW_WITH_UNRENDERED_VARS_%s%s%d%d_SUFFIX', '/PREFIX_(\d[A-Z])_NOW_WITH_UNRENDERED_VARS_%s%s%d%d_SUFFIX/'];
        yield 'unsupported placeholder stops further preview rendering' => ['ILLEGAL_VAR_STOPS_THE_CHAIN_%d%s%q%d%s_SUFFIX', '/ILLEGAL_VAR_STOPS_THE_CHAIN_(\d[A-Z])%q%d%s_SUFFIX/'];
    }

    public function testGenerateIndividualCodesWith0RequestedCodes(): void
    {
        $pattern = 'PREFIX_%s%d%s%d_SUFFIX';
        $codeList = $this->codesService->generateIndividualCodes($pattern, 0);

        static::assertCount(0, $codeList);
    }

    #[DataProvider('generateIndividualCodesDataProvider')]
    public function testGenerateIndividualCodesWithValidRequirements(int $requestedAmount): void
    {
        $pattern = 'PREFIX_%s%d%s%d_SUFFIX';
        $expectedCodeLength = \strlen(str_replace('%', '', $pattern));
        $codeList = $this->codesService->generateIndividualCodes($pattern, $requestedAmount);
        $codeLengthList = array_map(static fn ($code) => \strlen($code), $codeList);

        static::assertCount($requestedAmount, $codeList);
        static::assertCount($requestedAmount, array_unique($codeList));
        static::assertCount(1, array_unique($codeLengthList));
        static::assertSame($expectedCodeLength, $codeLengthList[0]);
    }

    /**
     * @return iterable<array<int>>
     */
    public static function generateIndividualCodesDataProvider(): iterable
    {
        yield 'generate individual codes 1' => [1];
        yield 'generate individual codes 20000' => [20000];
    }

    #[DataProvider('generateIndividualCodesWithInsufficientPatternDataProvider')]
    public function testGenerateIndividualCodesWithInsufficientPattern(int $requestedCodeAmount): void
    {
        // Only has 10 possibilities -> 6 or more requested codes would be invalid
        $pattern = 'PREFIX_%d_SUFFIX';

        $this->expectExceptionObject(PromotionException::patternNotComplexEnough());
        $this->codesService->generateIndividualCodes($pattern, $requestedCodeAmount);
    }

    /**
     * @return iterable<array<int>>
     */
    public static function generateIndividualCodesWithInsufficientPatternDataProvider(): iterable
    {
        yield 'generate individual codes with insufficient pattern 6' => [6];
        yield 'generate individual codes with insufficient pattern 20' => [20];
    }

    public function testReplaceIndividualCodes(): void
    {
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $codeRepository = static::getContainer()->get('promotion_individual_code.repository');
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $context = $channelContext->getContext();

        $id = Uuid::randomHex();
        $codes = ['myIndividualCode_00A', 'myIndividualCode_11B'];
        $this->createPromotion($id, null, $promotionRepository, $channelContext);
        $this->createIndividualCode($id, $codes[0], $codeRepository, $context);
        $this->createIndividualCode($id, $codes[1], $codeRepository, $context);

        $criteria = (new Criteria([$id]))
            ->addAssociation('individualCodes');

        /** @var PromotionEntity|null $promotion */
        $promotion = $promotionRepository->search($criteria, $context)->getEntities()->get($id);

        static::assertNotNull($promotion);
        static::assertNotNull($promotion->getIndividualCodes());
        static::assertCount(2, $promotion->getIndividualCodes()->getElements());

        $this->codesService->replaceIndividualCodes($id, 'newPattern_%d%d%s', 10, $context);

        /** @var PromotionEntity $promotion */
        $promotion = $promotionRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($promotion->getIndividualCodes());
        $individualCodes = $promotion->getIndividualCodes()->getElements();
        static::assertCount(10, $individualCodes);
        static::assertNotContains($codes[0], $individualCodes);
        static::assertNotContains($codes[1], $individualCodes);
    }

    public function testReplaceIndividualCodesWithDuplicatePattern(): void
    {
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $id = Uuid::randomHex();
        $duplicatePattern = 'TEST_%d%s_END';

        // Create 2 Promotions. The first one has a pattern, which the second will try to use as well later on
        $this->createPromotionWithCustomData(['individualCodePattern' => $duplicatePattern], $promotionRepository, $channelContext);
        $this->createPromotionWithCustomData(['id' => $id], $promotionRepository, $channelContext);

        $this->expectExceptionObject(PromotionException::patternAlreadyInUse());
        $this->codesService->replaceIndividualCodes($id, $duplicatePattern, 1, $channelContext->getContext());
    }

    public function testAddIndividualCodes(): void
    {
        $id = Uuid::randomHex();
        $pattern = 'somePattern_%d%d%d';
        $data = [
            'id' => $id,
            'useCodes' => true,
            'useIndividualCodes' => true,
            'individualCodePattern' => $pattern,
        ];
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $this->createPromotionWithCustomData($data, $promotionRepository, $channelContext);

        // 1000 possible codes -> 500 valid codes
        $this->codesService->replaceIndividualCodes($id, $pattern, 100, $channelContext->getContext());

        $this->addCodesAndAssertCount($id, 200, 300);
        $this->addCodesAndAssertCount($id, 200, 500);

        $this->expectExceptionObject(PromotionException::patternNotComplexEnough());
        $this->addCodesAndAssertCount($id, 1, 501);
    }

    public function testSplitPatternWithInvalidCodeThrowsInvalidCodePattern(): void
    {
        static::expectException(PromotionException::class);

        $this->codesService->splitPattern('PREFIX_%foo_SUFFIX');
    }

    private function addCodesAndAssertCount(string $id, int $newCodeAmount, int $expectedCodeAmount): void
    {
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $criteria = (new Criteria([$id]))
            ->addAssociation('individualCodes');

        $this->codesService->addIndividualCodes($id, $newCodeAmount, $channelContext->getContext());

        /** @var PromotionEntity|null $promotion */
        $promotion = $promotionRepository->search($criteria, $channelContext->getContext())->getEntities()->first();

        static::assertNotNull($promotion);
        static::assertNotNull($promotion->getIndividualCodes());
        static::assertCount($expectedCodeAmount, $promotion->getIndividualCodes()->getIds());
    }
}
