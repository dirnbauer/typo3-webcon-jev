<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use Webconsulting\WebconJev\Debug\DebugSettings;

/**
 * The switch is TypoScript, read from the request's own setup. Anything that is not a clear
 * "on" is off: the panel shows visitors their input and Jev's reasoning.
 */
final class DebugSettingsTest extends TestCase
{
    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function values(): iterable
    {
        yield 'switched on' => ['1', true];
        yield 'switched off' => ['0', false];
        yield 'empty, as an unset constant leaves it' => ['', false];
        yield 'a word' => ['yes please', false];
        yield 'not a scalar' => [['1'], false];
    }

    #[Test]
    #[DataProvider('values')]
    public function readsPluginTxWebconjevSettingsDebug(mixed $value, bool $enabled): void
    {
        $request = (new ServerRequest())->withAttribute('frontend.typoscript', self::typoScript([
            'plugin.' => ['tx_webconjev.' => ['settings.' => ['debug' => $value]]],
        ]));

        self::assertSame($enabled, (new DebugSettings())->isEnabled($request));
    }

    #[Test]
    public function aRequestWithoutFrontendTypoScriptIsOff(): void
    {
        self::assertFalse((new DebugSettings())->isEnabled(new ServerRequest()));
    }

    #[Test]
    public function aCachedPageWithoutSetupIsOff(): void
    {
        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $request = (new ServerRequest())->withAttribute('frontend.typoscript', $typoScript);

        self::assertFalse((new DebugSettings())->isEnabled($request));
    }

    #[Test]
    public function aSetupWithoutTheSettingIsOff(): void
    {
        $request = (new ServerRequest())->withAttribute('frontend.typoscript', self::typoScript(['plugin.' => []]));

        self::assertFalse((new DebugSettings())->isEnabled($request));
    }

    /**
     * @param array<string, mixed> $setup
     */
    private static function typoScript(array $setup): FrontendTypoScript
    {
        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupArray($setup);

        return $typoScript;
    }
}
