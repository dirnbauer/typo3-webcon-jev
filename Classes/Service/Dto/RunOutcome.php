<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service\Dto;

/**
 * What became of one run, as the run log tells it apart.
 */
enum RunOutcome: string
{
    /** Jev was asked and answered. The only outcome that costs anything. */
    case Answered = 'answered';

    /** An identical question about identical state was answered earlier; the API was not asked. */
    case Cached = 'cached';

    /** No usable answer — the decision's default outcome was used instead. */
    case Fallback = 'fallback';
}
