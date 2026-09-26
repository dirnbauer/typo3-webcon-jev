<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Powermail\JevOperator;

/**
 * How one powermail_cond rule read its Jev answer.
 */
final readonly class RuleTrace
{
    /**
     * @param string  $expected The option id, or the number the answer is compared with
     * @param ?Answer $answer   The raw answer, whether or not the rule was allowed to use it
     * @param bool    $usable   Whether the answer cleared the gate this rule applies
     */
    public function __construct(
        public int $ruleUid,
        public JevOperator $operator,
        public string $question,
        public string $expected,
        public ?Answer $answer,
        public bool $usable,
        public bool $result,
    ) {}
}
