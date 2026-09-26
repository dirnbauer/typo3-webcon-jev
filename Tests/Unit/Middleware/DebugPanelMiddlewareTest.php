<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use Webconsulting\WebconJev\Debug\DebugLog;
use Webconsulting\WebconJev\Debug\DebugPanelRenderer;
use Webconsulting\WebconJev\Debug\DebugPresenter;
use Webconsulting\WebconJev\Debug\DebugSettings;
use Webconsulting\WebconJev\Middleware\DebugPanelMiddleware;
use Webconsulting\WebconJev\Service\StateBuilder;

/**
 * Where the panel goes: into the condition endpoint's JSON, or onto the page a submission
 * renders — and nowhere at all unless the switch is on and Jev was actually asked.
 */
final class DebugPanelMiddlewareTest extends TestCase
{
    private const string PANEL = '<section class="wjd">PANEL</section>';

    private DebugLog $log;

    protected function setUp(): void
    {
        parent::setUp();
        $this->log = new DebugLog();
    }

    #[Test]
    public function theConditionJsonGainsTheRenderedPanel(): void
    {
        $this->log->note('something to show');
        $response = $this->pass(new JsonResponse(['todo' => ['948' => []], 'loops' => 2]), debug: true);

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame(['948' => []], $data['todo'], 'powermail_cond keeps its own keys');
        self::assertSame(['html' => self::PANEL], $data[DebugPanelMiddleware::JSON_KEY]);
        self::assertTrue($this->log->isEmpty(), 'the log is cleared once shown');
    }

    #[Test]
    public function aPageGetsThePanelBeforeTheClosingBodyTag(): void
    {
        $this->log->note('something to show');
        $response = $this->pass(new HtmlResponse('<html><body><main>Thanks</main></body></html>'), debug: true);

        self::assertSame(
            '<html><body><main>Thanks</main><div class="webcon-jev-debug-host" data-webcon-jev-debug="page">' . self::PANEL . '</div></body></html>',
            (string)$response->getBody(),
        );
    }

    #[Test]
    public function aContentLengthIsKeptTrue(): void
    {
        $this->log->note('something to show');
        $body = '<html><body>Thanks</body></html>';
        $response = $this->pass((new HtmlResponse($body))->withHeader('Content-Length', (string)strlen($body)), debug: true);

        self::assertSame((string)strlen((string)$response->getBody()), $response->getHeaderLine('Content-Length'));
    }

    #[Test]
    public function withTheSwitchOffNothingChangesAndTheLogIsCleared(): void
    {
        $this->log->note('something to show');
        $original = new JsonResponse(['todo' => []]);

        self::assertSame($original, $this->pass($original, debug: false));
        self::assertTrue($this->log->isEmpty());
    }

    #[Test]
    public function aRequestThatAskedJevNothingIsLeftAlone(): void
    {
        $original = new HtmlResponse('<html><body>Page</body></html>');

        self::assertSame($original, $this->pass($original, debug: true));
    }

    #[Test]
    public function aBodyThatIsNotJsonOrHtmlIsLeftAlone(): void
    {
        $this->log->note('something to show');
        $broken = new Response('php://temp', 200, ['Content-Type' => 'application/json']);
        $broken->getBody()->write('{not json');
        $image = new Response('php://temp', 200, ['Content-Type' => 'image/png']);

        self::assertSame($broken, $this->pass($broken, debug: true));
        $this->log->note('again');
        self::assertSame($image, $this->pass($image, debug: true));
    }

    private function pass(ResponseInterface $response, bool $debug): ResponseInterface
    {
        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupArray(['plugin.' => ['tx_webconjev.' => ['settings.' => ['debug' => $debug ? '1' : '0']]]]);
        $request = (new ServerRequest())->withAttribute('frontend.typoscript', $typoScript);

        $view = self::createStub(ViewInterface::class);
        $view->method('render')->willReturn(self::PANEL . "\n");
        $viewFactory = self::createStub(ViewFactoryInterface::class);
        $viewFactory->method('create')->willReturn($view);

        $middleware = new DebugPanelMiddleware(
            $this->log,
            new DebugSettings(),
            new DebugPanelRenderer($viewFactory, new DebugPresenter(new StateBuilder(), new ConnectionPool())),
            new StreamFactory(),
        );

        return $middleware->process($request, new readonly class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        });
    }
}
