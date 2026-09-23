<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing\Dto;

/**
 * One thing wrong with a draft, and where.
 *
 * The path names the field the way the editor addresses it — "title", "questions.1.name",
 * "questions.1.criteria.0.description", or "questions.1.criteria" for the list as a whole — so the
 * message lands next to the input it is about. The message itself is a label key in the
 * "webcon_jev.module" domain, translated for whoever is looking.
 */
final readonly class ValidationError
{
    /**
     * @param list<string|int> $arguments Values for the label's placeholders, in order
     */
    public function __construct(
        public string $path,
        public string $labelKey,
        public array $arguments = [],
    ) {}
}
