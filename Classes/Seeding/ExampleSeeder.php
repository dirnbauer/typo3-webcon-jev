<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Seeding;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Data\JevExampleDefinitions;
use Webconsulting\WebconJev\Powermail\JevOperator;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Builds the five lab examples: decisions, powermail forms, conditions and the pages showing them.
 *
 * Everything it writes is marked as its own — pages by slug prefix, forms and decisions by a
 * marker — and a second run removes what the first one made before making it again. That keeps
 * the examples a reproducible demonstration rather than a pile that grows.
 *
 * The markers are deliberately different from the ones EXT:desiderio's own powermail seeder uses,
 * so reseeding the styleguide cannot hide these pages and reseeding these cannot touch those.
 *
 * The Powermail Lab page itself belongs to EXT:desiderio, which lists its own six forms there.
 * These five get a section of their own below that list. A styleguide reseed replaces all
 * content on the lab page, this section included, so run this seeder again after it.
 */
final readonly class ExampleSeeder
{
    public const SLUG_PREFIX = '/desiderio-powermail-jev/';
    public const FORM_MARKER = 'webcon-jev-demo';
    /** Kept in tt_content.rowDescription, where an editor sees it in the page module. */
    public const OVERVIEW_MARKER = 'Seeded by webcon-jev:examples:seed. A reseed replaces it.';

    private const FORM = 'tx_powermail_domain_model_form';
    private const FORM_PAGE = 'tx_powermail_domain_model_page';
    private const FIELD = 'tx_powermail_domain_model_field';
    private const CONTAINER = 'tx_powermailcond_domain_model_conditioncontainer';
    private const CONDITION = 'tx_powermailcond_domain_model_condition';
    private const RULE = 'tx_powermailcond_domain_model_rule';
    private const DECISION = 'tx_webconjev_decision';
    private const QUESTION = 'tx_webconjev_question';
    private const CRITERION = 'tx_webconjev_criterion';

    public function __construct(
        private ConnectionPool $connectionPool,
        private SchemaHelper $schema,
    ) {}

    /**
     * @return list<string> Tables the examples need but this installation does not have
     */
    public function missingTables(): array
    {
        $missing = [];
        foreach ([self::FORM, self::FORM_PAGE, self::FIELD, self::CONTAINER, self::CONDITION, self::RULE] as $table) {
            if (!$this->schema->tableExists($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    /**
     * @return array{examples: int, forms: int, decisions: int, conditions: int, pages: int}
     */
    public function seed(int $labPageUid, int $germanLanguageUid, SymfonyStyle $io): array
    {
        $now = time();
        $this->removePreviouslySeeded();

        $counts = ['examples' => 0, 'forms' => 0, 'decisions' => 0, 'conditions' => 0, 'pages' => 0];
        $sorting = 4096;
        $overview = [];

        foreach (JevExampleDefinitions::all() as $example) {
            $decisionUid = $this->seedDecision($example['decision'], $germanLanguageUid, $now);
            $counts['decisions']++;

            $form = $this->seedForm($example, $labPageUid, $germanLanguageUid, $now);
            $counts['forms']++;

            $this->connectionPool->getConnectionForTable(self::FORM)->update(
                self::FORM,
                $this->schema->filter(self::FORM, [
                    'tx_webconjev_routing_decision' => $decisionUid,
                    'tx_webconjev_routing_question' => $example['routingQuestion'],
                ]),
                ['uid' => $form['uid']],
            );

            $counts['conditions'] += $this->seedConditions($example, $form, $decisionUid, $labPageUid, $now);

            $sorting += 256;
            $overview[] = [
                'pageUid' => $this->seedPages($example, $form, $labPageUid, $germanLanguageUid, $sorting, $now),
                'example' => $example,
            ];
            $counts['pages'] += 2;
            $counts['examples']++;

            $io->writeln(sprintf(
                '  <info>%s</info> %s — decision %d, form %d',
                $example['number'],
                $example['slug'],
                $decisionUid,
                $form['uid'],
            ));
        }

        $this->seedOverview($labPageUid, $germanLanguageUid, $overview, $now);

        return $counts;
    }

    /**
     * One linked entry per example on the lab page, in the same shape as EXT:desiderio's list of
     * its own forms above it.
     *
     * The German row sits on the lab page too: translated content belongs to the page it
     * translates, not to the page's translation record, or TYPO3 never shows it.
     *
     * @param list<array{pageUid: int, example: array<string, mixed>}> $overview
     */
    private function seedOverview(int $labPageUid, int $germanLanguageUid, array $overview, int $now): void
    {
        $englishUid = $this->insertOverview(
            $labPageUid,
            'Five forms that decide with Jev',
            'In these forms, Jev reads what the visitor writes. Its answers show or hide fields and decide who receives the mail.'
            . ' Without a confident answer, the mail goes to the default address.',
            $overview,
            false,
            $now,
        );
        $this->insertOverview(
            $labPageUid,
            'Fünf Formulare, die mit Jev entscheiden',
            'In diesen Formularen liest Jev, was Besucher schreiben. Die Antworten blenden Felder ein oder aus und entscheiden, wer die Nachricht erhält.'
            . ' Ohne sichere Antwort geht sie an die Standardadresse.',
            $overview,
            true,
            $now,
            $germanLanguageUid,
            $englishUid,
        );
    }

    /**
     * @param list<array{pageUid: int, example: array<string, mixed>}> $overview
     */
    private function insertOverview(
        int $labPageUid,
        string $header,
        string $note,
        array $overview,
        bool $german,
        int $now,
        int $languageUid = 0,
        int $translationParent = 0,
    ): int {
        $items = '';
        foreach ($overview as $entry) {
            $example = $entry['example'];
            $items .= sprintf(
                '<li><p><strong><a href="t3://page?uid=%d">%s</a></strong><br>%s</p></li>',
                $entry['pageUid'],
                htmlspecialchars(Cast::string($example[$german ? 'titleDe' : 'titleEn'] ?? null)),
                htmlspecialchars(Cast::string($example[$german ? 'summaryDe' : 'summaryEn'] ?? null)),
            );
        }

        return $this->insert('tt_content', [
            'pid' => $labPageUid,
            'CType' => 'text',
            'header' => $header,
            'bodytext' => '<p>' . htmlspecialchars($note) . '</p><ul>' . $items . '</ul>',
            'rowDescription' => self::OVERVIEW_MARKER,
            'colPos' => 0,
            // Below EXT:desiderio's list of its own forms (384).
            'sorting' => 512,
            'sys_language_uid' => $languageUid,
            'l18n_parent' => $translationParent,
            'l10n_parent' => $translationParent,
            'l10n_source' => $translationParent,
            'crdate' => $now,
            'tstamp' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function seedDecision(array $decision, int $germanLanguageUid, int $now): int
    {
        $questions = Cast::array($decision['questions'] ?? null);

        $uid = $this->insert(self::DECISION, [
            'pid' => 0,
            'identifier' => Cast::string($decision['identifier'] ?? null),
            'title' => Cast::string($decision['titleEn'] ?? null),
            'description' => Cast::string($decision['descriptionEn'] ?? null),
            'state_template' => Cast::string($decision['stateTemplate'] ?? null),
            'confidence_threshold' => Cast::float($decision['threshold'] ?? null, 0.6),
            'cache_lifetime' => -1,
            'default_outcome' => Cast::string($decision['defaultOutcome'] ?? null),
            'questions' => count($questions),
            'sorting' => 256,
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        $translationUid = $this->insert(self::DECISION, [
            'pid' => 0,
            'identifier' => Cast::string($decision['identifier'] ?? null),
            'title' => Cast::string($decision['titleDe'] ?? null),
            'description' => Cast::string($decision['descriptionDe'] ?? null),
            'state_template' => Cast::string($decision['stateTemplate'] ?? null),
            'confidence_threshold' => Cast::float($decision['threshold'] ?? null, 0.6),
            'cache_lifetime' => -1,
            'default_outcome' => Cast::string($decision['defaultOutcome'] ?? null),
            'questions' => count($questions),
            'sorting' => 256,
            'sys_language_uid' => $germanLanguageUid,
            'l10n_parent' => $uid,
            'l10n_source' => $uid,
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        foreach ($questions as $index => $rawQuestion) {
            $question = Cast::map($rawQuestion);
            $criteria = Cast::array($question['criteria'] ?? null);

            $questionUid = $this->insert(self::QUESTION, [
                'pid' => 0,
                'decision' => $uid,
                'name' => Cast::string($question['name'] ?? null),
                'type' => Cast::string($question['type'] ?? null),
                'instructions' => Cast::string($question['en'] ?? null),
                'criteria' => count($criteria),
                'sorting' => (Cast::int($index) + 1) * 256,
                'crdate' => $now,
                'tstamp' => $now,
            ]);
            $questionTranslationUid = $this->insert(self::QUESTION, [
                'pid' => 0,
                'decision' => $translationUid,
                'name' => Cast::string($question['name'] ?? null),
                'type' => Cast::string($question['type'] ?? null),
                'instructions' => Cast::string($question['de'] ?? null),
                'criteria' => count($criteria),
                'sorting' => (Cast::int($index) + 1) * 256,
                'sys_language_uid' => $germanLanguageUid,
                'l10n_parent' => $questionUid,
                'l10n_source' => $questionUid,
                'crdate' => $now,
                'tstamp' => $now,
            ]);

            foreach ($criteria as $criterionIndex => $rawCriterion) {
                $criterion = Cast::map($rawCriterion);
                $criterionUid = $this->insert(self::CRITERION, [
                    'pid' => 0,
                    'question' => $questionUid,
                    'identifier' => Cast::string($criterion['id'] ?? null),
                    'description' => Cast::string($criterion['en'] ?? null),
                    'outcome_value' => Cast::string($criterion['outcome'] ?? null),
                    'sorting' => (Cast::int($criterionIndex) + 1) * 256,
                    'crdate' => $now,
                    'tstamp' => $now,
                ]);
                $this->insert(self::CRITERION, [
                    'pid' => 0,
                    'question' => $questionTranslationUid,
                    'identifier' => Cast::string($criterion['id'] ?? null),
                    'description' => Cast::string($criterion['de'] ?? null),
                    'outcome_value' => Cast::string($criterion['outcome'] ?? null),
                    'sorting' => (Cast::int($criterionIndex) + 1) * 256,
                    'sys_language_uid' => $germanLanguageUid,
                    'l10n_parent' => $criterionUid,
                    'l10n_source' => $criterionUid,
                    'crdate' => $now,
                    'tstamp' => $now,
                ]);
            }
        }

        return $uid;
    }

    /**
     * @param array<string, mixed> $example
     *
     * @return array{uid: int, translationUid: int, pageUids: list<int>, fieldUids: array<string, int>}
     */
    private function seedForm(array $example, int $storagePid, int $germanLanguageUid, int $now): array
    {
        $pages = Cast::array($example['pages'] ?? null);
        $css = self::FORM_MARKER . ' webcon-jev-' . Cast::string($example['slug'] ?? null);

        $formUid = $this->insert(self::FORM, [
            'pid' => $storagePid,
            'title' => Cast::string($example['titleEn'] ?? null),
            'css' => $css,
            'pages' => count($pages),
            'autocomplete_token' => 'on',
            'crdate' => $now,
            'tstamp' => $now,
        ]);
        $formTranslationUid = $this->insert(self::FORM, [
            'pid' => $storagePid,
            'title' => Cast::string($example['titleDe'] ?? null),
            'css' => $css,
            'pages' => count($pages),
            'autocomplete_token' => 'on',
            'sys_language_uid' => $germanLanguageUid,
            'l10n_parent' => $formUid,
            'l10n_source' => $formUid,
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        $pageUids = [];
        $fieldUids = [];

        foreach ($pages as $pageIndex => $rawPage) {
            $page = Cast::map($rawPage);
            $fields = Cast::array($page['fields'] ?? null);

            $pageUid = $this->insert(self::FORM_PAGE, [
                'pid' => $storagePid,
                'form' => $formUid,
                'title' => Cast::string($page['titleEn'] ?? null),
                'fields' => count($fields),
                'sorting' => (Cast::int($pageIndex) + 1) * 256,
                'crdate' => $now,
                'tstamp' => $now,
            ]);
            $pageTranslationUid = $this->insert(self::FORM_PAGE, [
                'pid' => $storagePid,
                'form' => $formTranslationUid,
                'title' => Cast::string($page['titleDe'] ?? null),
                'fields' => count($fields),
                'sorting' => (Cast::int($pageIndex) + 1) * 256,
                'sys_language_uid' => $germanLanguageUid,
                'l10n_parent' => $pageUid,
                'l10n_source' => $pageUid,
                'crdate' => $now,
                'tstamp' => $now,
            ]);
            $pageUids[] = $pageUid;

            foreach ($fields as $fieldIndex => $rawField) {
                $field = Cast::map($rawField);
                $marker = Cast::string($field['marker'] ?? null);
                $fieldUid = $this->insertField($storagePid, $pageUid, $field, false, Cast::int($fieldIndex), $now);
                $this->insertField(
                    $storagePid,
                    $pageTranslationUid,
                    $field,
                    true,
                    Cast::int($fieldIndex),
                    $now,
                    $germanLanguageUid,
                    $fieldUid,
                );
                $fieldUids[$marker] = $fieldUid;
            }
        }

        return [
            'uid' => $formUid,
            'translationUid' => $formTranslationUid,
            'pageUids' => $pageUids,
            'fieldUids' => $fieldUids,
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    private function insertField(
        int $storagePid,
        int $pageUid,
        array $field,
        bool $translated,
        int $index,
        int $now,
        int $languageUid = 0,
        int $l10nParent = 0,
    ): int {
        $mandatory = Cast::bool($field['mandatory'] ?? null);

        return $this->insert(self::FIELD, [
            'pid' => $storagePid,
            'page' => $pageUid,
            'title' => Cast::string($field[$translated ? 'titleDe' : 'titleEn'] ?? null),
            'type' => Cast::string($field['type'] ?? null),
            'marker' => Cast::string($field['marker'] ?? null),
            'own_marker_select' => 1,
            'mandatory' => $mandatory ? 1 : 0,
            'mandatory_text' => $mandatory
                ? ($translated ? 'Bitte füllen Sie dieses Feld aus.' : 'Please complete this field.')
                : '',
            'validation' => Cast::int($field['validation'] ?? null),
            'sender_email' => Cast::bool($field['sender_email'] ?? null) ? 1 : 0,
            'sender_name' => Cast::bool($field['sender_name'] ?? null) ? 1 : 0,
            'placeholder' => Cast::string($field[$translated ? 'placeholderDe' : 'placeholderEn'] ?? null),
            'prefill_value' => Cast::string($field['prefill'] ?? null),
            'settings' => $this->optionSettings(Cast::array($field['options'] ?? null), $translated),
            'text' => Cast::string($field[$translated ? 'textDe' : 'textEn'] ?? null),
            'sorting' => ($index + 1) * 256,
            'sys_language_uid' => $languageUid,
            'l10n_parent' => $l10nParent,
            'l10n_source' => $l10nParent,
            'crdate' => $now,
            'tstamp' => $now,
        ]);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function optionSettings(array $options, bool $translated): string
    {
        $lines = [];
        foreach ($options as $rawOption) {
            $option = Cast::array($rawOption);
            $lines[] = Cast::string($option[$translated ? 1 : 0] ?? null) . '|' . Cast::string($option[2] ?? null);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>                                                            $example
     * @param array{uid: int, translationUid: int, pageUids: list<int>, fieldUids: array<string, int>} $form
     */
    private function seedConditions(array $example, array $form, int $decisionUid, int $storagePid, int $now): int
    {
        $conditions = Cast::array($example['conditions'] ?? null);
        if ($conditions === []) {
            return 0;
        }

        $containerUid = $this->insert(self::CONTAINER, [
            'pid' => $storagePid,
            'title' => Cast::string($example['titleEn'] ?? null),
            'form' => $form['uid'],
            'conditions' => count($conditions),
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        foreach ($conditions as $index => $rawCondition) {
            $condition = Cast::map($rawCondition);
            $target = Cast::string($condition['target'] ?? null);
            $targetField = str_starts_with($target, 'fieldset:')
                ? 'fieldset:' . Cast::int($form['pageUids'][Cast::int(substr($target, 9)) - 1] ?? null)
                : (string)Cast::int($form['fieldUids'][$target] ?? null);

            $rules = Cast::array($condition['rules'] ?? null);
            $conditionUid = $this->insert(self::CONDITION, [
                'pid' => $storagePid,
                'conditioncontainer' => $containerUid,
                'title' => Cast::string($condition['titleEn'] ?? null),
                'target_field' => $targetField,
                // "Show only when" is an un-hide condition: powermail_cond negates a condition
                // whose rules do not match, which hides the target again.
                'actions' => Cast::bool($condition['show'] ?? null) ? 1 : 0,
                'conjunction' => 'AND',
                'rules' => count($rules),
                'sorting' => (Cast::int($index) + 1) * 256,
                'crdate' => $now,
                'tstamp' => $now,
            ]);

            foreach ($rules as $ruleIndex => $rawRule) {
                $rule = Cast::map($rawRule);
                $operator = $rule['operator'] instanceof JevOperator ? $rule['operator'] : JevOperator::ChoiceIs;

                $this->insert(self::RULE, [
                    'pid' => $storagePid,
                    'conditions' => $conditionUid,
                    'title' => sprintf('%s %s', Cast::string($rule['question'] ?? null), $operator->name),
                    'start_field' => Cast::int($form['fieldUids'][Cast::string($rule['start'] ?? null)] ?? null),
                    'ops' => $operator->value,
                    'cond_string' => '',
                    'equal_field' => 0,
                    'tx_webconjev_decision' => $decisionUid,
                    'tx_webconjev_question' => Cast::string($rule['question'] ?? null),
                    'tx_webconjev_expect' => Cast::string($rule['expect'] ?? null),
                    'tx_webconjev_threshold' => Cast::float($rule['threshold'] ?? null, 0.6),
                    'sorting' => (Cast::int($ruleIndex) + 1) * 256,
                    'crdate' => $now,
                    'tstamp' => $now,
                ]);
            }
        }

        return count($conditions);
    }

    /**
     * @param array<string, mixed>                                                            $example
     * @param array{uid: int, translationUid: int, pageUids: list<int>, fieldUids: array<string, int>} $form
     *
     * @return int The English page's uid
     */
    private function seedPages(
        array $example,
        array $form,
        int $labPageUid,
        int $germanLanguageUid,
        int $sorting,
        int $now,
    ): int {
        $slug = Cast::string($example['slug'] ?? null);

        $pageUid = $this->insert('pages', [
            'pid' => $labPageUid,
            'title' => Cast::string($example['titleEn'] ?? null),
            'doktype' => 1,
            'slug' => self::SLUG_PREFIX . $slug,
            'sorting' => $sorting,
            'crdate' => $now,
            'tstamp' => $now,
        ]);
        $this->insert('pages', [
            'pid' => $labPageUid,
            'title' => Cast::string($example['titleDe'] ?? null),
            'doktype' => 1,
            'slug' => self::SLUG_PREFIX . $slug . '-de',
            'sorting' => $sorting + 1,
            'sys_language_uid' => $germanLanguageUid,
            'l10n_parent' => $pageUid,
            'l10n_source' => $pageUid,
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        // The German elements belong to the English page, like the overview: content stored on
        // the page's translation record is never shown.
        $introUid = $this->insertText($pageUid, Cast::string($example['introEn'] ?? null), 256, $now);
        $this->insertText(
            $pageUid,
            Cast::string($example['introDe'] ?? null),
            256,
            $now,
            $germanLanguageUid,
            $introUid,
        );

        $moresteps = Cast::bool($example['moresteps'] ?? null);
        $pluginUid = $this->insertPlugin($pageUid, $form['uid'], $labPageUid, $moresteps, 512, $now);
        $this->insertPlugin(
            $pageUid,
            $form['translationUid'],
            $labPageUid,
            $moresteps,
            512,
            $now,
            $germanLanguageUid,
            $pluginUid,
        );

        return $pageUid;
    }

    private function insertText(
        int $pid,
        string $body,
        int $sorting,
        int $now,
        int $languageUid = 0,
        int $translationParent = 0,
    ): int {
        return $this->insert('tt_content', [
            'pid' => $pid,
            'CType' => 'text',
            'header' => '',
            'header_layout' => 100,
            'bodytext' => '<p>' . htmlspecialchars($body) . '</p>',
            'colPos' => 0,
            'sorting' => $sorting,
            'sys_language_uid' => $languageUid,
            'l18n_parent' => $translationParent,
            'l10n_parent' => $translationParent,
            'l10n_source' => $translationParent,
            'crdate' => $now,
            'tstamp' => $now,
        ]);
    }

    private function insertPlugin(
        int $pid,
        int $formUid,
        int $storagePid,
        bool $moresteps,
        int $sorting,
        int $now,
        int $languageUid = 0,
        int $translationParent = 0,
    ): int {
        return $this->insert('tt_content', [
            'pid' => $pid,
            'CType' => 'powermail_pi1',
            'header' => '',
            'header_layout' => 100,
            'pi_flexform' => $this->flexform($formUid, $storagePid, $moresteps),
            'colPos' => 0,
            'sorting' => $sorting,
            'sys_language_uid' => $languageUid,
            'l18n_parent' => $translationParent,
            'l10n_parent' => $translationParent,
            'l10n_source' => $translationParent,
            'crdate' => $now,
            'tstamp' => $now,
        ]);
    }

    private function flexform(int $formUid, int $storagePid, bool $moresteps): string
    {
        $sheets = [
            'main' => [
                'settings.flexform.main.form' => (string)$formUid,
                'settings.flexform.main.confirmation' => '0',
                'settings.flexform.main.optin' => '0',
                'settings.flexform.main.moresteps' => $moresteps ? '1' : '0',
                'settings.flexform.main.pid' => (string)$storagePid,
            ],
            'receiver' => [
                'settings.flexform.receiver.name' => 'Webconsulting',
                // Jev replaces this whenever it is sure enough; it is the fallback, not the plan.
                'settings.flexform.receiver.email' => 'office@webconsulting.at',
                'settings.flexform.receiver.subject' => 'Powermail form submission',
                'settings.flexform.receiver.body' => '{powermail_all}',
            ],
            'sender' => [
                'settings.flexform.sender.name' => 'Webconsulting',
                'settings.flexform.sender.email' => 'office@webconsulting.at',
                'settings.flexform.sender.subject' => 'Thank you for your request',
                'settings.flexform.sender.body' => 'Thank you. We received your request.',
            ],
            'thx' => [
                'settings.flexform.thx.body' => 'Thank you for your submission.',
            ],
        ];

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" standalone=\"yes\" ?>\n<T3FlexForms>\n    <data>\n";
        foreach ($sheets as $sheet => $fields) {
            $xml .= '        <sheet index="' . $sheet . "\">\n            <language index=\"lDEF\">\n";
            foreach ($fields as $field => $value) {
                $xml .= '                <field index="' . htmlspecialchars($field, ENT_XML1)
                    . '"><value index="vDEF">' . htmlspecialchars($value, ENT_XML1) . "</value></field>\n";
            }
            $xml .= "            </language>\n        </sheet>\n";
        }

        return $xml . "    </data>\n</T3FlexForms>";
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): int
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $connection->insert($table, $this->schema->filter($table, $row));

        return (int)$connection->lastInsertId();
    }

    /**
     * Remove what an earlier run of this seeder made, so a reseed replaces rather than duplicates.
     */
    private function removePreviouslySeeded(): void
    {
        $formUids = $this->uidsWhere(self::FORM, 'css LIKE :marker', ['marker' => self::FORM_MARKER . '%']);
        $pageUids = $formUids === [] ? [] : $this->uidsIn(self::FORM_PAGE, 'form', $formUids);
        $fieldUids = $pageUids === [] ? [] : $this->uidsIn(self::FIELD, 'page', $pageUids);
        $containerUids = $formUids === [] ? [] : $this->uidsIn(self::CONTAINER, 'form', $formUids);
        $conditionUids = $containerUids === [] ? [] : $this->uidsIn(self::CONDITION, 'conditioncontainer', $containerUids);
        $ruleUids = $conditionUids === [] ? [] : $this->uidsIn(self::RULE, 'conditions', $conditionUids);

        $this->deleteUids(self::RULE, $ruleUids);
        $this->deleteUids(self::CONDITION, $conditionUids);
        $this->deleteUids(self::CONTAINER, $containerUids);
        $this->deleteUids(self::FIELD, $fieldUids);
        $this->deleteUids(self::FORM_PAGE, $pageUids);
        $this->deleteUids(self::FORM, $formUids);

        $this->deleteUids('tt_content', $this->uidsWhere('tt_content', 'rowDescription = :marker', ['marker' => self::OVERVIEW_MARKER]));

        $contentPageUids = $this->uidsWhere('pages', 'slug LIKE :slug', ['slug' => self::SLUG_PREFIX . '%']);
        if ($contentPageUids !== []) {
            $this->deleteUids('tt_content', $this->uidsIn('tt_content', 'pid', $contentPageUids));
            $this->deleteUids('pages', $contentPageUids);
        }

        $identifiers = array_map(
            static fn(array $example): string => Cast::string(Cast::map($example['decision'] ?? null)['identifier'] ?? null),
            JevExampleDefinitions::all(),
        );
        $decisionUids = $this->uidsWhereIn(self::DECISION, 'identifier', $identifiers);
        $questionUids = $decisionUids === [] ? [] : $this->uidsIn(self::QUESTION, 'decision', $decisionUids);
        $criterionUids = $questionUids === [] ? [] : $this->uidsIn(self::CRITERION, 'question', $questionUids);

        $this->deleteUids(self::CRITERION, $criterionUids);
        $this->deleteUids(self::QUESTION, $questionUids);
        $this->deleteUids(self::DECISION, $decisionUids);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<int>
     */
    private function uidsWhere(string $table, string $where, array $parameters): array
    {
        if (!$this->schema->tableExists($table)) {
            return [];
        }

        $rows = $this->connectionPool
            ->getConnectionForTable($table)
            ->executeQuery('SELECT uid FROM ' . $table . ' WHERE ' . $where, $parameters)
            ->fetchFirstColumn();

        return array_values(array_map(static fn(mixed $uid): int => Cast::int($uid), $rows));
    }

    /**
     * @param list<int> $parentUids
     *
     * @return list<int>
     */
    private function uidsIn(string $table, string $column, array $parentUids): array
    {
        if ($parentUids === [] || !$this->schema->tableExists($table)) {
            return [];
        }

        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();
        $rows = $query
            ->select('uid')
            ->from($table)
            ->where($query->expr()->in($column, $query->createNamedParameter($parentUids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_map(static fn(mixed $uid): int => Cast::int($uid), $rows));
    }

    /**
     * @param list<string> $values
     *
     * @return list<int>
     */
    private function uidsWhereIn(string $table, string $column, array $values): array
    {
        if ($values === [] || !$this->schema->tableExists($table)) {
            return [];
        }

        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();
        $rows = $query
            ->select('uid')
            ->from($table)
            ->where($query->expr()->in($column, $query->createNamedParameter($values, Connection::PARAM_STR_ARRAY)))
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_map(static fn(mixed $uid): int => Cast::int($uid), $rows));
    }

    /**
     * @param list<int> $uids
     */
    private function deleteUids(string $table, array $uids): void
    {
        if ($uids === [] || !$this->schema->tableExists($table)) {
            return;
        }

        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $query
            ->delete($table)
            ->where($query->expr()->in('uid', $query->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeStatement();
    }
}
