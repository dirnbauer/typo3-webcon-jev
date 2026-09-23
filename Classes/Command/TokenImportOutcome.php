<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

/**
 * What "webcon-jev:token:import" did. Three outcomes, not two: collapsing "already there, left
 * alone" into the same answer as "stored" once made the command report a store it had not done.
 */
enum TokenImportOutcome
{
    case Created;
    case Rotated;
    case Skipped;
}
