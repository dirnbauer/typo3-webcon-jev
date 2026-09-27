<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Data;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Data\JevExampleDefinitions;
use Webconsulting\WebconJev\Powermail\JevOperator;

/**
 * The examples are data, and a slip in data fails quietly: a rule naming a question the decision
 * does not ask never matches, a noul rule at 0.5 fires on a coin flip, and a submit field on step 1
 * of a multi-step form sends it from there. Each of these shipped once, or nearly.
 *
 * @phpstan-import-type Example from JevExampleDefinitions
 */
final class JevExampleDefinitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{Example}>
     */
    public static function examples(): iterable
    {
        foreach (JevExampleDefinitions::all() as $example) {
            yield $example['slug'] => [$example];
        }
    }

    /**
     * @param Example $example
     */
    #[Test]
    #[DataProvider('examples')]
    public function aFormSubmitsFromItsLastPageOnly(array $example): void
    {
        $submitsPerPage = array_map(
            static fn(array $page): int => count(array_filter($page['fields'], static fn(array $field): bool => $field['type'] === 'submit')),
            $example['pages'],
        );

        $expected = array_fill(0, count($submitsPerPage), 0);
        $expected[array_key_last($expected)] = 1;
        self::assertSame($expected, $submitsPerPage);
        self::assertSame(count($example['pages']) > 1, $example['moresteps'], 'more than one page means a multi-step form');
    }

    /**
     * @param Example $example
     */
    #[Test]
    #[DataProvider('examples')]
    public function everyConditionReadsWhatTheFormAndTheDecisionHave(array $example): void
    {
        $markers = [];
        foreach ($example['pages'] as $page) {
            foreach ($page['fields'] as $field) {
                $markers[] = $field['marker'];
            }
        }
        $questions = [];
        foreach ($example['decision']['questions'] as $question) {
            $questions[$question['name']] = $question;
        }

        self::assertIsList($example['conditions']);
        foreach ($example['conditions'] as $condition) {
            $label = $example['slug'] . ': ' . $condition['titleEn'];
            self::assertContains($condition['target'], $markers, $label . ' targets a field of the form');
            self::assertContains($condition['conjunction'] ?? 'AND', ['AND', 'OR'], $label);
            self::assertNotSame([], $condition['rules'], $label);

            foreach ($condition['rules'] as $rule) {
                self::assertContains($rule['start'], $markers, $label . ' starts from a field of the form');
                $question = $questions[$rule['question']] ?? null;
                self::assertNotNull($question, $label . ' asks a question of the decision');
                self::assertSame($rule['operator']->expects(), QuestionType::from($question['type']), $label . ' compares an answer of its own type');

                if ($rule['operator'] === JevOperator::ChoiceIs || $rule['operator'] === JevOperator::ChoiceIsNot) {
                    self::assertContains($rule['expect'], array_column($question['criteria'], 'id'), $label . ' expects an option the question has');
                }
                if ($rule['operator'] === JevOperator::NoulAbove) {
                    self::assertGreaterThan(0.5, $rule['threshold'], $label . ': a noul rule at 0.5 fires on a coin flip');
                }
                if ($rule['operator'] === JevOperator::NoulBelow) {
                    self::assertLessThan(0.5, $rule['threshold'], $label . ': a noul rule at 0.5 fires on a coin flip');
                }
            }
        }
    }

    /**
     * @param Example $example
     */
    #[Test]
    #[DataProvider('examples')]
    public function routingNamesAChoiceWhoseOptionsAreAddresses(array $example): void
    {
        $routing = null;
        foreach ($example['decision']['questions'] as $question) {
            if ($question['name'] === $example['routingQuestion']) {
                $routing = $question;
            }
        }

        self::assertNotNull($routing, 'the routing question is asked');
        self::assertSame(QuestionType::Choice->value, $routing['type']);
        foreach ($routing['criteria'] as $criterion) {
            self::assertTrue(GeneralUtility::validEmail($criterion['outcome']), $criterion['id'] . ' routes to an address');
        }
        self::assertTrue(GeneralUtility::validEmail($example['decision']['defaultOutcome']), 'unsure answers route to an address');
    }
}
