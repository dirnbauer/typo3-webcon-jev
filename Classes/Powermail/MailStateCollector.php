<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use In2code\Powermail\Domain\Model\Answer;
use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Mail;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Turns a submitted powermail mail into a context for Jev.
 *
 * Same shape as {@see FormStateCollector} produces for a half-filled form, so one decision reads
 * the same state whether it runs live while somebody types or once at submit time.
 */
final readonly class MailStateCollector
{
    private const array SENSITIVE_TYPES = ['password', 'file', 'captcha', 'friendlycaptcha'];

    /**
     * @return array{form: array{uid: int, title: string}, field: array<string, string>}
     */
    public function collect(Mail $mail): array
    {
        $fields = [];

        foreach ($mail->getAnswersByFieldMarker() as $marker => $answer) {
            if (!$answer instanceof Answer) {
                continue;
            }
            $field = $answer->getField();
            if ($field === null || in_array(strtolower($field->getType()), self::SENSITIVE_TYPES, true)) {
                continue;
            }
            $value = trim($answer->getStringValue());
            if ($value === '' || Cast::string($marker) === '') {
                continue;
            }
            $fields[Cast::string($marker)] = $value;
        }

        $form = $mail->getForm();
        $form = $form instanceof Form ? $form : null;

        return [
            'form' => [
                'uid' => Cast::int($form?->getUid()),
                'title' => $form?->getTitle() ?? '',
            ],
            'field' => $fields,
        ];
    }
}
