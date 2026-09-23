<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Page;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Turns a powermail form, with whatever the visitor has typed so far, into a context for Jev.
 *
 * Fields are keyed by marker, so a state template reads {{field.message}} and a decision survives
 * a field being renamed in the backend as long as its marker stays.
 */
final readonly class FormStateCollector
{
    /** Never send these to a third-party API, whatever the form calls them. */
    private const array SENSITIVE_TYPES = ['password', 'file', 'captcha', 'friendlycaptcha'];

    /**
     * @return array{form: array{uid: int, title: string}, field: array<string, string>}
     */
    public function collect(Form $form): array
    {
        $fields = [];

        /** @var Page $page */
        foreach ($form->getPages() as $page) {
            /** @var Field $field */
            foreach ($page->getFields() as $field) {
                if (in_array(strtolower($field->getType()), self::SENSITIVE_TYPES, true)) {
                    continue;
                }
                $value = trim($this->stringify($field->getText()));
                if ($value === '') {
                    continue;
                }
                $marker = $field->getMarker();
                if ($marker === '') {
                    continue;
                }
                $fields[$marker] = $value;
            }
        }

        return [
            'form' => ['uid' => Cast::int($form->getUid()), 'title' => $form->getTitle()],
            'field' => $fields,
        ];
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map($this->stringify(...), $value));
        }
        if ($value === null || is_bool($value)) {
            return '';
        }

        return is_int($value) || is_float($value) || is_string($value) ? (string)$value : '';
    }
}
