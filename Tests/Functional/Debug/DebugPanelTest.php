<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Debug;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Debug\DebugLog;
use Webconsulting\WebconJev\Debug\DebugPanelRenderer;
use Webconsulting\WebconJev\Debug\DecisionTrace;
use Webconsulting\WebconJev\Debug\RuleTrace;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Powermail\JevOperator;
use Webconsulting\WebconJev\Service\DecisionOutcome;

/**
 * The real template and presenter, with powermail_cond's rows in the database: every rule is
 * named by its condition and says which field it shows or hides.
 */
final class DebugPanelTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['backend', 'install', 'frontend', 'extbase', 'fluid', 'scheduler'];

    protected array $testExtensionsToLoad = ['nr_vault', 'powermail', 'powermail_cond', 'webcon_jev'];

    #[Test]
    public function rendersAnswersAndRulesInEnglish(): void
    {
        $html = $this->render('en');

        self::assertStringContainsString('Jev debug', $html);
        self::assertStringContainsString('Live conditions', $html);
        self::assertStringContainsString('jev_support_triage', $html);
        self::assertStringContainsString('Ask for reproduction steps only for a bug report', $html);
        self::assertStringContainsString('When it applies, the form shows “How can we reproduce it?”.', $html);
        self::assertStringContainsString('When it applies, the form hides “Send report”.', $html);
        self::assertStringContainsString('is_bug ≥ 0.60', $html);
        self::assertStringContainsString('no confidence gate', $html, 'a noul rule is gated on its own number');
        self::assertStringContainsString('effort &lt; 1.50', $html);
        self::assertStringContainsString('confidence 40 %, below 0.55: the answer is not used', $html);
        self::assertStringContainsString('frontend-debug.css', $html, 'the fragment brings its own stylesheet');
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString(' style="', $html, 'nothing a strict CSP would refuse');
    }

    #[Test]
    public function speaksGermanOnAGermanPage(): void
    {
        $html = $this->render('de');

        self::assertStringContainsString('Live-Bedingungen', $html);
        self::assertStringContainsString('trifft zu', $html);
        self::assertStringContainsString('Wenn sie zutrifft, zeigt das Formular „How can we reproduce it?“.', $html);
    }

    private function render(string $locale): string
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/debug_rules.csv');

        $decision = new Decision(1, 'jev_support_triage', 'Support triage', '', 'Message: {{field.message}}', '', 0.55, -1, '', [
            new DecisionQuestion(1, 'is_bug', QuestionType::Noul, 'Is it a bug?', [
                new Criterion(1, 'yes', 'Broken'),
                new Criterion(2, 'no', 'A question'),
            ]),
            new DecisionQuestion(2, 'effort', QuestionType::Score, 'How much is there to answer?', [
                new Criterion(3, '', 'Nothing: empty or noise.'),
                new Criterion(4, '', 'Something: a real question.'),
                new Criterion(5, '', 'A lot: a detailed request.'),
            ]),
        ]);
        $isBug = Answer::fromResponse('is_bug', QuestionType::Noul, ['noul' => 0.98]);
        $effort = Answer::fromResponse('effort', QuestionType::Score, ['score' => 0.2, 'confidence' => 0.4, 'probabilities' => [0.8, 0.2, 0.0]]);
        $outcome = new DecisionOutcome($decision, new DecisionResult(['is_bug' => $isBug, 'effort' => $effort], 'jev-1.13.0', new Usage(500), 380.0));

        $log = new DebugLog();
        $trace = $log->trace('conditions:1:948', new DecisionTrace(DecisionTrace::CONDITIONS, $outcome, ['field' => ['message' => 'It crashes']]));
        $trace->addRule(new RuleTrace(7, JevOperator::NoulAbove, 'is_bug', '0.6', $isBug, true, true));
        $trace->addRule(new RuleTrace(8, JevOperator::ScoreBelow, 'effort', '1.5', $effort, false, false));

        $request = new ServerRequest(new Uri('https://example.test/'), 'GET', 'php://input', [], ['HTTP_HOST' => 'example.test', 'HTTPS' => 'on']);
        $request = $request
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            ->withAttribute('language', new SiteLanguage($locale === 'de' ? 1 : 0, $locale === 'de' ? 'de_AT.UTF-8' : 'en_US.UTF-8', new Uri('/'), []));

        return $this->get(DebugPanelRenderer::class)->render($log, $request);
    }
}
