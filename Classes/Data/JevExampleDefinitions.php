<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Data;

use Webconsulting\WebconJev\Powermail\JevOperator;

/**
 * The five lab examples, as data.
 *
 * They are deliberately different from each other: one uses a single choice and nothing else, one
 * combines a yes/no probability with a severity score, one gates a submission, and two branch a
 * multi-step form on what the first step said. Between them they exercise all three primitives,
 * both integration points, and the confidence gate.
 *
 * @phpstan-type Option array{0: string, 1: string, 2: string}
 * @phpstan-type ExampleField array{type: string, marker: string, titleEn: string, titleDe: string, mandatory: bool, validation: int, sender_email: bool, sender_name: bool, placeholderEn: string, placeholderDe: string, prefill: string, options: list<Option>, textEn: string, textDe: string}
 * @phpstan-type ExamplePage array{titleEn: string, titleDe: string, fields: list<ExampleField>}
 * @phpstan-type Criterion array{id: string, en: string, de: string, outcome: string}
 * @phpstan-type Question array{name: string, type: string, en: string, de: string, criteria: list<Criterion>}
 * @phpstan-type Rule array{start: string, operator: JevOperator, question: string, expect: string, threshold: float}
 * @phpstan-type Condition array{titleEn: string, titleDe: string, target: string, show: bool, rules: list<Rule>}
 * @phpstan-type Example array{slug: string, number: string, titleEn: string, titleDe: string, introEn: string, introDe: string, summaryEn: string, summaryDe: string, moresteps: bool, routingQuestion: string, decision: array{identifier: string, titleEn: string, titleDe: string, descriptionEn: string, descriptionDe: string, stateTemplate: string, threshold: float, defaultOutcome: string, questions: list<Question>}, pages: list<ExamplePage>, conditions: list<Condition>}
 */
final class JevExampleDefinitions
{
    /**
     * @return list<Example>
     */
    public static function all(): array
    {
        return [
            self::contactRouting(),
            self::supportTriage(),
            self::qualityGate(),
            self::projectEnquiry(),
            self::jobApplication(),
        ];
    }

    /**
     * The simplest useful thing: one question, and the answer decides who gets the mail.
     *
     * @return Example
     */
    private static function contactRouting(): array
    {
        return [
            'slug' => 'contact-routing',
            'number' => '07',
            'titleEn' => 'Powermail 07: Smart contact routing',
            'titleDe' => 'Powermail 07: Intelligente Kontaktweiterleitung',
            'introEn' => 'One contact form, five departments, no dropdown asking the visitor which one they need. Jev reads the message and the submission goes to whoever should answer it — and to the default address whenever it is not sure enough.',
            'introDe' => 'Ein Kontaktformular, fünf Abteilungen, kein Auswahlfeld, das den Besucher fragt, welche er braucht. Jev liest die Nachricht, und die Einsendung geht an die zuständige Stelle — und an die Standardadresse, sobald es zu unsicher ist.',
            'summaryEn' => 'Jev reads the message and sends it to the right department. When it is unsure, the mail goes to the default address.',
            'summaryDe' => 'Jev liest die Nachricht und schickt sie an die zuständige Abteilung. Ist es unsicher, geht sie an die Standardadresse.',
            'moresteps' => false,
            'routingQuestion' => 'department',
            'decision' => [
                'identifier' => 'jev_contact_department',
                'titleEn' => 'Contact form: which department',
                'titleDe' => 'Kontaktformular: welche Abteilung',
                'descriptionEn' => 'Reads a contact message and names the department that should answer it.',
                'descriptionDe' => 'Liest eine Kontaktnachricht und benennt die Abteilung, die antworten soll.',
                'stateTemplate' => "Subject: {{field.subject}}\nMessage: {{field.message}}",
                'threshold' => 0.6,
                'defaultOutcome' => 'office@webconsulting.at',
                'questions' => [
                    [
                        'name' => 'department',
                        'type' => 'choice',
                        'en' => 'Which department should answer this enquiry?',
                        'de' => 'Welche Abteilung soll diese Anfrage beantworten?',
                        'criteria' => [
                            ['id' => 'sales', 'en' => 'Wants to buy something, asks about prices, a quote, or starting a project.', 'de' => 'Will etwas kaufen, fragt nach Preisen, einem Angebot oder einem Projektstart.', 'outcome' => 'sales@webconsulting.at'],
                            ['id' => 'support', 'en' => 'Something is broken or not working as expected, or they need help using it.', 'de' => 'Etwas ist kaputt oder funktioniert nicht wie erwartet, oder es wird Hilfe bei der Bedienung gebraucht.', 'outcome' => 'support@webconsulting.at'],
                            ['id' => 'accounting', 'en' => 'About an invoice, a payment, a refund, a reminder or billing details.', 'de' => 'Betrifft eine Rechnung, eine Zahlung, eine Rückerstattung, eine Mahnung oder Rechnungsdaten.', 'outcome' => 'accounting@webconsulting.at'],
                            ['id' => 'jobs', 'en' => 'About working here: an application, an internship, a CV, or asking whether a role is open.', 'de' => 'Betrifft eine Mitarbeit: Bewerbung, Praktikum, Lebenslauf oder die Frage nach offenen Stellen.', 'outcome' => 'jobs@webconsulting.at'],
                            ['id' => 'press', 'en' => 'A journalist or media enquiry: an interview, a quote for an article, or press material.', 'de' => 'Eine Presse- oder Medienanfrage: Interview, Zitat für einen Artikel oder Pressematerial.', 'outcome' => 'press@webconsulting.at'],
                        ],
                    ],
                ],
            ],
            'pages' => [
                [
                    'titleEn' => 'Your enquiry',
                    'titleDe' => 'Ihre Anfrage',
                    'fields' => [
                        self::field('input', 'name', 'Name', 'Name', ['mandatory' => true, 'sender_name' => true]),
                        self::field('input', 'email', 'Email', 'E-Mail', ['mandatory' => true, 'validation' => 1, 'sender_email' => true]),
                        self::field('input', 'subject', 'Subject', 'Betreff', ['placeholderEn' => 'What is this about?', 'placeholderDe' => 'Worum geht es?']),
                        self::field('textarea', 'message', 'Message', 'Nachricht', ['mandatory' => true, 'placeholderEn' => 'Tell us what you need.', 'placeholderDe' => 'Sagen Sie uns, was Sie brauchen.']),
                        self::field('check', 'privacy', 'Privacy', 'Datenschutz', ['mandatory' => true, 'options' => [['I agree that this request may be processed.', 'Ich stimme der Verarbeitung dieser Anfrage zu.', 'accepted']]]),
                        self::field('submit', 'submit', 'Send enquiry', 'Anfrage senden'),
                    ],
                ],
            ],
            'conditions' => [],
        ];
    }

    /**
     * A yes/no probability and a severity score together, each driving a different field.
     *
     * @return Example
     */
    private static function supportTriage(): array
    {
        return [
            'slug' => 'support-triage',
            'number' => '08',
            'titleEn' => 'Powermail 08: Support triage',
            'titleDe' => 'Powermail 08: Support-Triage',
            'introEn' => 'The form asks for reproduction steps only when your text is a bug report. It warns about escalation only when the problem sounds serious. Two questions, two different primitives, both answered in the same call.',
            'introDe' => 'Das Formular fragt nur dann nach Reproduktionsschritten, wenn Ihr Text eine Fehlermeldung ist. Auf den Eskalationsweg weist es nur hin, wenn das Problem ernst klingt. Zwei Fragen, zwei verschiedene Primitive, beide im selben Aufruf beantwortet.',
            'summaryEn' => 'The form asks for reproduction steps only for a bug report. It shows the escalation path only when the problem sounds serious.',
            'summaryDe' => 'Das Formular fragt nur bei einer Fehlermeldung nach Reproduktionsschritten. Den Eskalationsweg zeigt es nur, wenn das Problem ernst klingt.',
            'moresteps' => false,
            'routingQuestion' => 'queue',
            'decision' => [
                'identifier' => 'jev_support_triage',
                'titleEn' => 'Support: is it a bug, and how bad',
                'titleDe' => 'Support: ist es ein Fehler, und wie schlimm',
                'descriptionEn' => 'Reads a support message and answers whether it reports a defect, how severe it is, and which queue it belongs in.',
                'descriptionDe' => 'Liest eine Support-Nachricht und beantwortet, ob sie einen Defekt meldet, wie schwer er ist und in welche Warteschlange sie gehört.',
                'stateTemplate' => "Product: {{field.product}}\nMessage: {{field.message}}",
                'threshold' => 0.55,
                'defaultOutcome' => 'support@webconsulting.at',
                'questions' => [
                    [
                        'name' => 'is_bug',
                        'type' => 'noul',
                        'en' => 'Is the writer reporting that something is broken, rather than asking how to do something?',
                        'de' => 'Meldet die schreibende Person, dass etwas kaputt ist, statt zu fragen, wie man etwas macht?',
                        'criteria' => [
                            ['id' => 'yes', 'en' => 'They describe behaviour that is wrong, failing, or different from what they expected.', 'de' => 'Es wird ein Verhalten beschrieben, das falsch ist, fehlschlägt oder anders ist als erwartet.', 'outcome' => ''],
                            ['id' => 'no', 'en' => 'They are asking a question, requesting a feature, or asking how something works.', 'de' => 'Es wird eine Frage gestellt, eine Funktion gewünscht oder gefragt, wie etwas funktioniert.', 'outcome' => ''],
                        ],
                    ],
                    [
                        'name' => 'severity',
                        'type' => 'score',
                        'en' => 'How badly is this blocking the writer from getting their work done?',
                        'de' => 'Wie stark hindert das die schreibende Person daran, ihre Arbeit zu erledigen?',
                        'criteria' => [
                            ['id' => '', 'en' => 'Not at all: a question, a suggestion, or something cosmetic.', 'de' => 'Gar nicht: eine Frage, ein Vorschlag oder etwas Kosmetisches.', 'outcome' => ''],
                            ['id' => '', 'en' => 'A little: it is awkward or slow, but there is a way around it.', 'de' => 'Ein wenig: es ist umständlich oder langsam, aber es gibt einen Umweg.', 'outcome' => ''],
                            ['id' => '', 'en' => 'A lot: something important does not work and there is no workaround.', 'de' => 'Stark: etwas Wichtiges funktioniert nicht und es gibt keinen Umweg.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Completely: the system is down, or data or money is at risk.', 'de' => 'Vollständig: das System steht still, oder Daten oder Geld sind in Gefahr.', 'outcome' => ''],
                        ],
                    ],
                    [
                        'name' => 'queue',
                        'type' => 'choice',
                        'en' => 'Who should pick this up first?',
                        'de' => 'Wer soll das zuerst übernehmen?',
                        'criteria' => [
                            ['id' => 'first_line', 'en' => 'Someone who can answer a usage question or point at documentation.', 'de' => 'Jemand, der eine Bedienfrage beantworten oder auf die Dokumentation verweisen kann.', 'outcome' => 'support@webconsulting.at'],
                            ['id' => 'engineering', 'en' => 'Someone who can read code: a defect, an error message, or something that needs a fix.', 'de' => 'Jemand, der Code lesen kann: ein Defekt, eine Fehlermeldung oder etwas, das behoben werden muss.', 'outcome' => 'engineering@webconsulting.at'],
                            ['id' => 'account', 'en' => 'Someone who handles the contract: access, licences, quotas or billing.', 'de' => 'Jemand, der den Vertrag betreut: Zugänge, Lizenzen, Kontingente oder Abrechnung.', 'outcome' => 'accounting@webconsulting.at'],
                        ],
                    ],
                ],
            ],
            'pages' => [
                [
                    'titleEn' => 'What happened',
                    'titleDe' => 'Was ist passiert',
                    'fields' => [
                        self::field('input', 'name', 'Name', 'Name', ['mandatory' => true, 'sender_name' => true]),
                        self::field('input', 'email', 'Email', 'E-Mail', ['mandatory' => true, 'validation' => 1, 'sender_email' => true]),
                        self::field('select', 'product', 'Product', 'Produkt', ['mandatory' => true, 'options' => [
                            ['Website', 'Website', 'website'],
                            ['Online shop', 'Online-Shop', 'shop'],
                            ['Intranet', 'Intranet', 'intranet'],
                            ['Something else', 'Etwas anderes', 'other'],
                        ]]),
                        self::field('textarea', 'message', 'What is the problem?', 'Was ist das Problem?', ['mandatory' => true, 'placeholderEn' => 'Describe what you did and what happened.', 'placeholderDe' => 'Beschreiben Sie, was Sie getan haben und was passiert ist.']),
                        self::field('textarea', 'reproduction', 'How can we reproduce it?', 'Wie können wir es nachstellen?', ['placeholderEn' => 'Step by step, if you can.', 'placeholderDe' => 'Schritt für Schritt, wenn möglich.']),
                        self::field('html', 'escalation', 'Escalation', 'Eskalation', [
                            'textEn' => '<p><strong>This looks urgent.</strong> If your system is down right now, call the support line on +43 1 234 5678 instead of waiting for a reply to this form.</p>',
                            'textDe' => '<p><strong>Das klingt dringend.</strong> Wenn Ihr System gerade stillsteht, rufen Sie die Support-Hotline unter +43 1 234 5678 an, statt auf eine Antwort auf dieses Formular zu warten.</p>',
                        ]),
                        self::field('submit', 'submit', 'Send report', 'Meldung senden'),
                    ],
                ],
            ],
            'conditions' => [
                [
                    'titleEn' => 'Ask for reproduction steps only for a bug report',
                    'titleDe' => 'Nur bei einer Fehlermeldung nach Reproduktionsschritten fragen',
                    'target' => 'reproduction',
                    'show' => true,
                    'rules' => [
                        ['start' => 'message', 'operator' => JevOperator::NoulAbove, 'question' => 'is_bug', 'expect' => '', 'threshold' => 0.6],
                    ],
                ],
                [
                    'titleEn' => 'Show the escalation path only when it reads as serious',
                    'titleDe' => 'Den Eskalationsweg nur zeigen, wenn es ernst klingt',
                    'target' => 'escalation',
                    'show' => true,
                    'rules' => [
                        ['start' => 'message', 'operator' => JevOperator::ScoreAtLeast, 'question' => 'severity', 'expect' => '', 'threshold' => 2.0],
                    ],
                ],
            ],
        ];
    }

    /**
     * A gate: the submit button itself is what a decision controls.
     *
     * @return Example
     */
    private static function qualityGate(): array
    {
        return [
            'slug' => 'quality-gate',
            'number' => '09',
            'titleEn' => 'Powermail 09: Spam and quality gate',
            'titleDe' => 'Powermail 09: Spam- und Qualitätsfilter',
            'introEn' => 'A captcha proves you are human; it says nothing about whether the message is worth sending. Here Jev reads the message itself: obvious spam and three words of effort both hide the send button, with a note saying what is missing. Everything borderline goes through — the gate is deliberately generous, because a false positive costs a real enquiry.',
            'introDe' => 'Ein Captcha beweist, dass Sie ein Mensch sind; über die Nachricht sagt es nichts. Hier liest Jev die Nachricht selbst: offensichtlicher Spam und drei Wörter Aufwand blenden den Senden-Button aus, mit einem Hinweis, was fehlt. Alles Grenzwertige geht durch — der Filter ist bewusst großzügig, weil ein Fehlalarm eine echte Anfrage kostet.',
            'summaryEn' => 'Obvious spam and messages with nothing to answer hide the send button, with a note on what is missing. Borderline messages go through.',
            'summaryDe' => 'Offensichtlicher Spam und Nachrichten ohne Inhalt blenden den Senden-Button aus, mit einem Hinweis, was fehlt. Grenzfälle gehen durch.',
            'moresteps' => false,
            'routingQuestion' => 'handling',
            'decision' => [
                'identifier' => 'jev_quality_gate',
                'titleEn' => 'Enquiry: is it spam, and is there anything to answer',
                'titleDe' => 'Anfrage: ist es Spam, und gibt es etwas zu beantworten',
                'descriptionEn' => 'Reads a message before it is sent and answers whether it is unsolicited advertising and whether it contains enough to reply to.',
                'descriptionDe' => 'Liest eine Nachricht vor dem Absenden und beantwortet, ob sie unerwünschte Werbung ist und ob sie genug enthält, um zu antworten.',
                'stateTemplate' => "Subject: {{field.subject}}\nMessage: {{field.message}}",
                'threshold' => 0.7,
                'defaultOutcome' => 'office@webconsulting.at',
                'questions' => [
                    [
                        'name' => 'is_spam',
                        'type' => 'noul',
                        'en' => 'Is this unsolicited advertising, rather than a genuine enquiry about this company?',
                        'de' => 'Ist das unerwünschte Werbung und keine echte Anfrage an dieses Unternehmen?',
                        'criteria' => [
                            ['id' => 'yes', 'en' => 'It sells SEO, backlinks, crypto, cheap development or web traffic, or it is a mass mailing.', 'de' => 'Es verkauft SEO, Backlinks, Krypto, billige Entwicklung oder Web-Traffic, oder es ist ein Massenmailing.', 'outcome' => ''],
                            ['id' => 'no', 'en' => 'It refers to something this company actually does, or asks a question a person would ask.', 'de' => 'Es bezieht sich auf etwas, das dieses Unternehmen tatsächlich tut, oder stellt eine Frage, die ein Mensch stellen würde.', 'outcome' => ''],
                        ],
                    ],
                    [
                        'name' => 'effort',
                        'type' => 'score',
                        'en' => 'How much is there here to actually answer?',
                        'de' => 'Wie viel gibt es hier wirklich zu beantworten?',
                        'criteria' => [
                            ['id' => '', 'en' => 'Nothing: empty, a greeting, or random characters.', 'de' => 'Nichts: leer, eine Begrüßung oder zufällige Zeichen.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Almost nothing: a few words with no subject, like "call me" or "info please".', 'de' => 'Fast nichts: ein paar Worte ohne Thema, etwa "Rufen Sie mich an" oder "Bitte Infos".', 'outcome' => ''],
                            ['id' => '', 'en' => 'Enough: a real question or request, even if short.', 'de' => 'Genug: eine echte Frage oder Bitte, auch wenn kurz.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Plenty: context, specifics, and a clear thing being asked for.', 'de' => 'Reichlich: Kontext, Details und ein klar benanntes Anliegen.', 'outcome' => ''],
                        ],
                    ],
                    [
                        'name' => 'handling',
                        'type' => 'choice',
                        'en' => 'Where should this land?',
                        'de' => 'Wo soll das landen?',
                        'criteria' => [
                            ['id' => 'inbox', 'en' => 'A genuine enquiry that a person should read.', 'de' => 'Eine echte Anfrage, die ein Mensch lesen soll.', 'outcome' => 'office@webconsulting.at'],
                            ['id' => 'quarantine', 'en' => 'Advertising or nonsense: keep it, but out of the way.', 'de' => 'Werbung oder Unsinn: aufheben, aber aus dem Weg.', 'outcome' => 'quarantine@webconsulting.at'],
                        ],
                    ],
                ],
            ],
            'pages' => [
                [
                    'titleEn' => 'Write to us',
                    'titleDe' => 'Schreiben Sie uns',
                    'fields' => [
                        self::field('input', 'name', 'Name', 'Name', ['mandatory' => true, 'sender_name' => true]),
                        self::field('input', 'email', 'Email', 'E-Mail', ['mandatory' => true, 'validation' => 1, 'sender_email' => true]),
                        self::field('input', 'subject', 'Subject', 'Betreff'),
                        self::field('textarea', 'message', 'Message', 'Nachricht', ['mandatory' => true, 'placeholderEn' => 'What would you like to ask us?', 'placeholderDe' => 'Was möchten Sie uns fragen?']),
                        self::field('html', 'thin', 'Too little', 'Zu wenig', [
                            'textEn' => '<p><strong>Could you say a little more?</strong> We cannot answer a message without a question in it. One sentence about what you need is enough — the send button comes back as soon as there is something to reply to.</p>',
                            'textDe' => '<p><strong>Mögen Sie noch etwas ergänzen?</strong> Eine Nachricht ohne Frage können wir nicht beantworten. Ein Satz dazu, was Sie brauchen, genügt — der Senden-Button kommt zurück, sobald es etwas zu beantworten gibt.</p>',
                        ]),
                        self::field('submit', 'submit', 'Send message', 'Nachricht senden'),
                    ],
                ],
            ],
            // Both conditions read the SAME rule, and both are the "hide" direction of it, so an
            // answer too uncertain to use leaves the button visible and the notice away rather
            // than hiding both. Written as two complementary "show when" conditions instead, a
            // low-confidence answer negates both at once and strands the visitor with no send
            // button and nothing explaining why — which is what this gate must never do.
            'conditions' => [
                [
                    'titleEn' => 'Hold the send button only when there is confidently nothing to answer',
                    'titleDe' => 'Senden-Button nur zurückhalten, wenn es sicher nichts zu beantworten gibt',
                    'target' => 'submit',
                    'show' => false,
                    'rules' => [
                        ['start' => 'message', 'operator' => JevOperator::ScoreBelow, 'question' => 'effort', 'expect' => '', 'threshold' => 1.5],
                    ],
                ],
                [
                    'titleEn' => 'Explain why the button is gone, whenever it is gone',
                    'titleDe' => 'Erklären, warum der Button fehlt, wann immer er fehlt',
                    'target' => 'thin',
                    'show' => true,
                    'rules' => [
                        ['start' => 'message', 'operator' => JevOperator::ScoreBelow, 'question' => 'effort', 'expect' => '', 'threshold' => 1.5],
                    ],
                ],
            ],
        ];
    }

    /**
     * A multi-step form where the first step decides what the later steps ask.
     *
     * @return Example
     */
    private static function projectEnquiry(): array
    {
        return [
            'slug' => 'project-enquiry',
            'number' => '10',
            'titleEn' => 'Powermail 10: Project enquiry that branches',
            'titleDe' => 'Powermail 10: Projektanfrage, die sich verzweigt',
            'introEn' => 'Describe the project in your own words on step one. What you wrote decides what step two asks. A small job never sees the procurement questions, and the NDA block appears only when the description suggests one. Nobody picks a category from a dropdown.',
            'introDe' => 'Beschreiben Sie das Projekt auf Schritt eins in eigenen Worten. Das Geschriebene entscheidet, was Schritt zwei fragt. Ein kleiner Auftrag sieht die Beschaffungsfragen nie, und der NDA-Block erscheint nur, wenn die Beschreibung darauf hindeutet. Niemand wählt eine Kategorie aus einer Liste.',
            'summaryEn' => 'What you write on the first step decides which questions come next. Nobody picks a category from a list.',
            'summaryDe' => 'Was Sie im ersten Schritt schreiben, entscheidet, welche Fragen danach kommen. Niemand wählt eine Kategorie aus einer Liste.',
            'moresteps' => true,
            'routingQuestion' => 'owner',
            'decision' => [
                'identifier' => 'jev_project_enquiry',
                'titleEn' => 'Project enquiry: size, fit and formality',
                'titleDe' => 'Projektanfrage: Größe, Passung und Formalität',
                'descriptionEn' => 'Reads a project description and answers how big it is, how well it fits what we do, whether it needs an NDA, and who should own it.',
                'descriptionDe' => 'Liest eine Projektbeschreibung und beantwortet, wie groß sie ist, wie gut sie zu uns passt, ob eine Geheimhaltungsvereinbarung nötig ist und wer sie übernehmen soll.',
                'stateTemplate' => "Company: {{field.company}}\nProject: {{field.project}}",
                'threshold' => 0.6,
                'defaultOutcome' => 'sales@webconsulting.at',
                'questions' => [
                    [
                        'name' => 'size',
                        'type' => 'choice',
                        'en' => 'How large a piece of work does this description suggest?',
                        'de' => 'Wie groß ist die Arbeit, auf die diese Beschreibung hindeutet?',
                        'criteria' => [
                            ['id' => 'small', 'en' => 'A single change, a fix, or a few days of work on something that exists.', 'de' => 'Eine einzelne Änderung, eine Korrektur oder wenige Tage Arbeit an etwas Bestehendem.', 'outcome' => 'sales@webconsulting.at'],
                            ['id' => 'medium', 'en' => 'A defined project: a site, a shop, an integration, weeks to a few months.', 'de' => 'Ein umrissenes Projekt: eine Website, ein Shop, eine Integration, Wochen bis wenige Monate.', 'outcome' => 'sales@webconsulting.at'],
                            ['id' => 'large', 'en' => 'A programme: several systems, several teams, procurement, or a public tender.', 'de' => 'Ein Programm: mehrere Systeme, mehrere Teams, Beschaffung oder eine öffentliche Ausschreibung.', 'outcome' => 'bids@webconsulting.at'],
                        ],
                    ],
                    [
                        'name' => 'fit',
                        'type' => 'score',
                        'en' => 'How well does this match what a TYPO3 agency does?',
                        'de' => 'Wie gut passt das zu dem, was eine TYPO3-Agentur macht?',
                        'criteria' => [
                            ['id' => '', 'en' => 'Not at all: a different industry, or nothing to do with software.', 'de' => 'Gar nicht: eine andere Branche oder nichts mit Software zu tun.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Loosely: software, but not web, or a technology we do not work in.', 'de' => 'Entfernt: Software, aber nicht Web, oder eine Technologie, mit der wir nicht arbeiten.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Well: a web project we could take on.', 'de' => 'Gut: ein Webprojekt, das wir übernehmen könnten.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Exactly: TYPO3, content management, or something named in our portfolio.', 'de' => 'Genau: TYPO3, Content-Management oder etwas aus unserem Portfolio.', 'outcome' => ''],
                        ],
                    ],
                    [
                        'name' => 'needs_nda',
                        'type' => 'noul',
                        'en' => 'Does this description suggest the work would need a confidentiality agreement before details are shared?',
                        'de' => 'Deutet diese Beschreibung darauf hin, dass vor der Weitergabe von Details eine Geheimhaltungsvereinbarung nötig wäre?',
                        'criteria' => [
                            ['id' => 'yes', 'en' => 'It mentions something unreleased, internal, regulated, or explicitly confidential.', 'de' => 'Es wird etwas Unveröffentlichtes, Internes, Reguliertes oder ausdrücklich Vertrauliches erwähnt.', 'outcome' => ''],
                            ['id' => 'no', 'en' => 'It describes ordinary public-facing work.', 'de' => 'Es beschreibt gewöhnliche, öffentlich sichtbare Arbeit.', 'outcome' => ''],
                        ],
                    ],
                    [
                        'name' => 'owner',
                        'type' => 'choice',
                        'en' => 'Who should own this enquiry?',
                        'de' => 'Wer soll diese Anfrage übernehmen?',
                        'criteria' => [
                            ['id' => 'sales', 'en' => 'An ordinary commercial enquiry.', 'de' => 'Eine gewöhnliche kommerzielle Anfrage.', 'outcome' => 'sales@webconsulting.at'],
                            ['id' => 'bids', 'en' => 'A tender or procurement process with formal requirements.', 'de' => 'Eine Ausschreibung oder ein Beschaffungsverfahren mit formalen Anforderungen.', 'outcome' => 'bids@webconsulting.at'],
                            ['id' => 'partners', 'en' => 'Another agency or supplier proposing to work together.', 'de' => 'Eine andere Agentur oder ein Lieferant, der eine Zusammenarbeit vorschlägt.', 'outcome' => 'partners@webconsulting.at'],
                        ],
                    ],
                ],
            ],
            'pages' => [
                [
                    'titleEn' => 'Tell us about the project',
                    'titleDe' => 'Erzählen Sie uns vom Projekt',
                    'fields' => [
                        self::field('input', 'company', 'Company', 'Unternehmen', ['mandatory' => true]),
                        self::field('textarea', 'project', 'What would you like built?', 'Was möchten Sie umsetzen lassen?', ['mandatory' => true, 'placeholderEn' => 'In your own words — we will work out the rest.', 'placeholderDe' => 'In Ihren eigenen Worten — den Rest klären wir.']),
                        self::field('submit', 'next1', 'Continue', 'Weiter'),
                    ],
                ],
                [
                    'titleEn' => 'A few details',
                    'titleDe' => 'Ein paar Details',
                    'fields' => [
                        self::field('select', 'timing', 'When should it start?', 'Wann soll es starten?', ['options' => [
                            ['As soon as possible', 'So bald wie möglich', 'asap'],
                            ['Within three months', 'Innerhalb von drei Monaten', 'quarter'],
                            ['This year', 'Dieses Jahr', 'year'],
                            ['Not decided yet', 'Noch offen', 'open'],
                        ]]),
                        self::field('input', 'tender', 'Tender or reference number', 'Ausschreibungs- oder Aktenzeichen', ['placeholderEn' => 'If this is a formal procurement.', 'placeholderDe' => 'Falls es eine formale Beschaffung ist.']),
                        self::field('textarea', 'procurement', 'Which formal requirements apply?', 'Welche formalen Anforderungen gelten?', ['placeholderEn' => 'Award criteria, deadlines, required certifications.', 'placeholderDe' => 'Zuschlagskriterien, Fristen, geforderte Zertifikate.']),
                        self::field('check', 'nda', 'Confidentiality', 'Vertraulichkeit', ['options' => [['Please send a confidentiality agreement before we go into detail.', 'Bitte senden Sie vor der Detailklärung eine Geheimhaltungsvereinbarung.', 'yes']]]),
                        self::field('submit', 'next2', 'Continue', 'Weiter'),
                    ],
                ],
                [
                    'titleEn' => 'How do we reach you?',
                    'titleDe' => 'Wie erreichen wir Sie?',
                    'fields' => [
                        self::field('input', 'name', 'Name', 'Name', ['mandatory' => true, 'sender_name' => true]),
                        self::field('input', 'email', 'Email', 'E-Mail', ['mandatory' => true, 'validation' => 1, 'sender_email' => true]),
                        self::field('input', 'phone', 'Phone', 'Telefon'),
                        self::field('check', 'privacy', 'Privacy', 'Datenschutz', ['mandatory' => true, 'options' => [['I agree that this request may be processed.', 'Ich stimme der Verarbeitung dieser Anfrage zu.', 'accepted']]]),
                        self::field('submit', 'submit', 'Send enquiry', 'Anfrage senden'),
                    ],
                ],
            ],
            'conditions' => [
                [
                    'titleEn' => 'Ask for a reference number only for a large procurement',
                    'titleDe' => 'Nur bei großer Beschaffung nach einem Aktenzeichen fragen',
                    'target' => 'tender',
                    'show' => true,
                    'rules' => [
                        ['start' => 'project', 'operator' => JevOperator::ChoiceIs, 'question' => 'size', 'expect' => 'large', 'threshold' => 0.6],
                    ],
                ],
                [
                    'titleEn' => 'Ask about formal requirements only for a large procurement',
                    'titleDe' => 'Nur bei großer Beschaffung nach formalen Anforderungen fragen',
                    'target' => 'procurement',
                    'show' => true,
                    'rules' => [
                        ['start' => 'project', 'operator' => JevOperator::ChoiceIs, 'question' => 'size', 'expect' => 'large', 'threshold' => 0.6],
                    ],
                ],
                [
                    'titleEn' => 'Offer the confidentiality agreement only when it reads as needed',
                    'titleDe' => 'Die Geheimhaltungsvereinbarung nur anbieten, wenn sie nötig scheint',
                    'target' => 'nda',
                    'show' => true,
                    'rules' => [
                        ['start' => 'project', 'operator' => JevOperator::NoulAbove, 'question' => 'needs_nda', 'expect' => '', 'threshold' => 0.5],
                    ],
                ],
            ],
        ];
    }

    /**
     * The most involved: three primitives, a branching second step, and routing that admits when
     * it does not know.
     *
     * @return Example
     */
    private static function jobApplication(): array
    {
        return [
            'slug' => 'job-application',
            'number' => '11',
            'titleEn' => 'Powermail 11: Job application, routed by its text',
            'titleDe' => 'Powermail 11: Bewerbung, nach Inhalt zugeordnet',
            'introEn' => 'Paste what you would put in a covering letter. Jev reads it for the role, the seniority and whether you ask to work remotely. Step two then asks only the questions that follow from those answers. Routing is confidence-gated: below the threshold, a person sorts the application and the run log says so. For a decision about someone\'s career, that is the honest behaviour.',
            'introDe' => 'Fügen Sie ein, was Sie in ein Anschreiben schreiben würden. Jev liest daraus die Rolle, die Erfahrungsstufe und ob Sie um Remote-Arbeit bitten. Schritt zwei stellt dann nur die Fragen, die sich daraus ergeben. Die Weiterleitung ist konfidenzgesteuert: Unterhalb der Schwelle sichtet ein Mensch die Bewerbung, und das Protokoll sagt das auch. Bei einer Entscheidung über jemandes Laufbahn ist das ehrlich.',
            'summaryEn' => 'Jev reads the covering letter for role, seniority and remote work, then asks only the questions that follow. A person sorts unclear cases.',
            'summaryDe' => 'Jev liest aus dem Anschreiben Rolle, Erfahrung und den Wunsch nach Remote-Arbeit und stellt nur die passenden Fragen. Unklare Fälle sichtet ein Mensch.',
            'moresteps' => true,
            'routingQuestion' => 'role',
            'decision' => [
                'identifier' => 'jev_job_application',
                'titleEn' => 'Application: role, seniority and working remotely',
                'titleDe' => 'Bewerbung: Rolle, Erfahrungsstufe und Remote-Arbeit',
                'descriptionEn' => 'Reads a covering letter and answers which team it belongs to, how experienced the writer is, and whether they are asking to work remotely.',
                'descriptionDe' => 'Liest ein Anschreiben und beantwortet, zu welchem Team es gehört, wie erfahren die schreibende Person ist und ob sie um Remote-Arbeit bittet.',
                'stateTemplate' => "Applying for: {{field.position}}\nLetter: {{field.letter}}",
                'threshold' => 0.75,
                'defaultOutcome' => 'jobs@webconsulting.at',
                'questions' => [
                    [
                        'name' => 'role',
                        'type' => 'choice',
                        'en' => 'Which team does this application belong to?',
                        'de' => 'Zu welchem Team gehört diese Bewerbung?',
                        'criteria' => [
                            ['id' => 'engineering', 'en' => 'Writes software: backend, frontend, TYPO3, infrastructure.', 'de' => 'Schreibt Software: Backend, Frontend, TYPO3, Infrastruktur.', 'outcome' => 'engineering-hiring@webconsulting.at'],
                            ['id' => 'design', 'en' => 'Design and user experience: interfaces, brand, accessibility.', 'de' => 'Design und Nutzererlebnis: Oberflächen, Marke, Barrierefreiheit.', 'outcome' => 'design-hiring@webconsulting.at'],
                            ['id' => 'project', 'en' => 'Runs projects and clients: planning, coordination, consulting.', 'de' => 'Führt Projekte und Kunden: Planung, Koordination, Beratung.', 'outcome' => 'pm-hiring@webconsulting.at'],
                            ['id' => 'apprentice', 'en' => 'Still learning: an internship, an apprenticeship, or a first job.', 'de' => 'Noch in Ausbildung: Praktikum, Lehre oder erster Job.', 'outcome' => 'apprentice-hiring@webconsulting.at'],
                        ],
                    ],
                    [
                        'name' => 'seniority',
                        'type' => 'score',
                        'en' => 'How much professional experience does the letter describe?',
                        'de' => 'Wie viel Berufserfahrung beschreibt das Anschreiben?',
                        'criteria' => [
                            ['id' => '', 'en' => 'None yet: still studying, or looking for a first placement.', 'de' => 'Noch keine: im Studium oder auf der Suche nach dem ersten Einsatz.', 'outcome' => ''],
                            ['id' => '', 'en' => 'A little: one or two years, or a first job out of training.', 'de' => 'Wenig: ein bis zwei Jahre oder der erste Job nach der Ausbildung.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Solid: several years of doing the work independently.', 'de' => 'Solide: mehrere Jahre eigenständige Arbeit.', 'outcome' => ''],
                            ['id' => '', 'en' => 'Senior: leading work, mentoring others, or owning architecture.', 'de' => 'Senior: führt Arbeit, betreut andere oder verantwortet Architektur.', 'outcome' => ''],
                        ],
                    ],
                    // One thing, not two. An earlier version asked whether the writer "is willing
                    // to move OR is already nearby", and a letter reading "fully remotely from
                    // Graz" satisfied both halves at once: Jev answered 0.42 at confidence 0.16,
                    // which is the model correctly saying the question cannot be answered as put.
                    [
                        'name' => 'wants_remote',
                        'type' => 'noul',
                        'en' => 'Does the letter ask to work remotely, or say the writer cannot regularly come to an office?',
                        'de' => 'Bittet das Anschreiben um Remote-Arbeit oder sagt es, dass die schreibende Person nicht regelmäßig in ein Büro kommen kann?',
                        'criteria' => [
                            ['id' => 'yes', 'en' => 'They ask for remote or home-office work, or say they cannot commute to an office.', 'de' => 'Es wird um Remote- oder Homeoffice-Arbeit gebeten oder gesagt, dass ein Arbeitsweg ins Büro nicht möglich ist.', 'outcome' => ''],
                            ['id' => 'no', 'en' => 'They do not raise it, or they expect to work on site.', 'de' => 'Es wird nicht angesprochen, oder es wird Arbeit vor Ort erwartet.', 'outcome' => ''],
                        ],
                    ],
                ],
            ],
            'pages' => [
                [
                    'titleEn' => 'Why you',
                    'titleDe' => 'Warum Sie',
                    'fields' => [
                        self::field('input', 'position', 'Which position?', 'Welche Position?', ['placeholderEn' => 'Leave empty if you are not sure — we will work it out.', 'placeholderDe' => 'Leer lassen, wenn Sie unsicher sind — wir finden es heraus.']),
                        self::field('textarea', 'letter', 'Tell us about yourself', 'Erzählen Sie von sich', ['mandatory' => true, 'placeholderEn' => 'What you have done, what you want to do next.', 'placeholderDe' => 'Was Sie gemacht haben und was Sie als Nächstes tun möchten.']),
                        self::field('submit', 'next1', 'Continue', 'Weiter'),
                    ],
                ],
                [
                    'titleEn' => 'A little more',
                    'titleDe' => 'Noch etwas',
                    'fields' => [
                        self::field('input', 'portfolio', 'Portfolio or repository', 'Portfolio oder Repository', ['placeholderEn' => 'A link to something you made.', 'placeholderDe' => 'Ein Link zu etwas, das Sie gemacht haben.']),
                        self::field('textarea', 'leadership', 'Tell us about leading a piece of work', 'Erzählen Sie von einer Arbeit, die Sie geführt haben', ['placeholderEn' => 'Something you owned end to end, and what you would do differently.', 'placeholderDe' => 'Etwas, das Sie von Anfang bis Ende verantwortet haben, und was Sie anders machen würden.']),
                        self::field('select', 'school', 'Where are you studying or training?', 'Wo studieren oder lernen Sie gerade?', ['options' => [
                            ['University', 'Universität', 'university'],
                            ['University of applied sciences', 'Fachhochschule', 'fh'],
                            ['HTL or vocational school', 'HTL oder Berufsschule', 'htl'],
                            ['Self-taught', 'Autodidaktisch', 'self'],
                        ]]),
                        self::field('html', 'relocation_note', 'Location', 'Standort', [
                            'textEn' => '<p><strong>Before you go further:</strong> most of the team is in Vienna two days a week. Some roles work fully remotely and some do not, and we would rather tell you which is which now than after three interviews. Say where you are based and we will be straight with you.</p>',
                            'textDe' => '<p><strong>Bevor Sie weitermachen:</strong> der größte Teil des Teams ist zwei Tage pro Woche in Wien. Manche Rollen funktionieren vollständig remote, manche nicht, und wir sagen Ihnen das lieber jetzt als nach drei Gesprächen. Schreiben Sie uns, wo Sie sind, dann sind wir ehrlich zu Ihnen.</p>',
                        ]),
                        self::field('submit', 'next2', 'Continue', 'Weiter'),
                    ],
                ],
                [
                    'titleEn' => 'How do we reach you?',
                    'titleDe' => 'Wie erreichen wir Sie?',
                    'fields' => [
                        self::field('input', 'name', 'Name', 'Name', ['mandatory' => true, 'sender_name' => true]),
                        self::field('input', 'email', 'Email', 'E-Mail', ['mandatory' => true, 'validation' => 1, 'sender_email' => true]),
                        self::field('check', 'privacy', 'Privacy', 'Datenschutz', ['mandatory' => true, 'options' => [['I agree that my application may be processed.', 'Ich stimme der Verarbeitung meiner Bewerbung zu.', 'accepted']]]),
                        self::field('submit', 'submit', 'Send application', 'Bewerbung senden'),
                    ],
                ],
            ],
            'conditions' => [
                [
                    'titleEn' => 'Ask for a portfolio from engineering and design',
                    'titleDe' => 'Portfolio bei Entwicklung und Design erfragen',
                    'target' => 'portfolio',
                    'show' => true,
                    'rules' => [
                        ['start' => 'letter', 'operator' => JevOperator::ChoiceIsNot, 'question' => 'role', 'expect' => 'project', 'threshold' => 0.6],
                    ],
                ],
                [
                    'titleEn' => 'Ask the leadership question only of experienced applicants',
                    'titleDe' => 'Die Führungsfrage nur erfahrenen Bewerbenden stellen',
                    'target' => 'leadership',
                    'show' => true,
                    'rules' => [
                        ['start' => 'letter', 'operator' => JevOperator::ScoreAtLeast, 'question' => 'seniority', 'expect' => '', 'threshold' => 2.0],
                    ],
                ],
                [
                    'titleEn' => 'Ask where they study only when there is no experience yet',
                    'titleDe' => 'Nur ohne Berufserfahrung nach dem Ausbildungsort fragen',
                    'target' => 'school',
                    'show' => true,
                    'rules' => [
                        ['start' => 'letter', 'operator' => JevOperator::ScoreBelow, 'question' => 'seniority', 'expect' => '', 'threshold' => 1.0],
                    ],
                ],
                [
                    'titleEn' => 'Address working remotely only when the letter asks about it',
                    'titleDe' => 'Remote-Arbeit nur ansprechen, wenn das Anschreiben danach fragt',
                    'target' => 'relocation_note',
                    'show' => true,
                    'rules' => [
                        ['start' => 'letter', 'operator' => JevOperator::NoulAbove, 'question' => 'wants_remote', 'expect' => '', 'threshold' => 0.6],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return ExampleField
     */
    private static function field(string $type, string $marker, string $titleEn, string $titleDe, array $overrides = []): array
    {
        /** @var ExampleField $field */
        $field = $overrides + [
            'type' => $type,
            'marker' => $marker,
            'titleEn' => $titleEn,
            'titleDe' => $titleDe,
            'mandatory' => false,
            'validation' => 0,
            'sender_email' => false,
            'sender_name' => false,
            'placeholderEn' => '',
            'placeholderDe' => '',
            'prefill' => '',
            'options' => [],
            'textEn' => '',
            'textDe' => '',
        ];

        return $field;
    }
}
