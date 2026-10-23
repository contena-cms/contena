<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(ContenaStyle::class)]
class ContenaStyleTest extends TestCase
{
    use EnvTestBehaviour;

    #[TestDox('createProgressBar throws, because the class deprecation is enforced when v6.8.0.0 is active')]
    public function testCreateProgressBarThrowsWhenV68IsActive(): void
    {
        $style = new ContenaStyle(new ArrayInput([]), new BufferedOutput());

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(ContenaStyle::class, 'v6.8.0.0')
        ));
        $style->createProgressBar();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('createProgressBar applies the magenta bar character')]
    public function testCreateProgressBarAppliesBarCharacter(): void
    {
        $style = new ContenaStyle(new ArrayInput([]), new BufferedOutput());

        $progressBar = $style->createProgressBar(10);

        static::assertSame('<fg=magenta>=</>', $progressBar->getBarCharacter());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('createProgressBar takes the progress character from the PROGRESS_BAR_CHARACTER env variable')]
    public function testCreateProgressBarUsesProgressCharacterFromEnvironment(): void
    {
        $this->setEnvVars(['PROGRESS_BAR_CHARACTER' => '#']);
        $style = new ContenaStyle(new ArrayInput([]), new BufferedOutput());

        $progressBar = $style->createProgressBar(10);

        static::assertSame('#', $progressBar->getProgressCharacter());
    }
}
