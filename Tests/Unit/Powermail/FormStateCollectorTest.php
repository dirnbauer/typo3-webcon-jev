<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Powermail;

use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Page;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Webconsulting\WebconJev\Powermail\FormStateCollector;

/**
 * The state a condition reads while the visitor types has to match the one routing reads from
 * the submitted mail, or a decision without a state template answers two different questions.
 */
final class FormStateCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // powermail's Form::getPages() asks its extension configuration how pages are related.
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['powermail'] = ['replaceIrreWithElementBrowser' => '0'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['powermail']);
        parent::tearDown();
    }

    #[Test]
    public function onlyWhatTheVisitorEnteredIsCollected(): void
    {
        $page = new Page();
        $page->addField($this->field('input', 'name', 'Ada'));
        $page->addField($this->field('textarea', 'message', '  The shop is down.  '));
        $page->addField($this->field('input', 'subject', ''));
        // What an editor wrote, not the visitor: a submitted mail has no answer for these.
        $page->addField($this->field('html', 'escalation', '<p>This looks urgent.</p>'));
        $page->addField($this->field('text', 'notice', 'Fields marked * are required.'));
        $page->addField($this->field('submit', 'submit', 'Send'));
        // Never leaves the site, whatever the visitor typed.
        $page->addField($this->field('password', 'secret', 'hunter2'));

        $pages = new ObjectStorage();
        $pages->attach($page);
        $form = new Form();
        $form->setTitle('Support');
        $form->setPages($pages);

        $state = (new FormStateCollector())->collect($form);

        self::assertSame(['name' => 'Ada', 'message' => 'The shop is down.'], $state['field']);
        self::assertSame('Support', $state['form']['title']);
    }

    private function field(string $type, string $marker, string $text): Field
    {
        $field = new Field();
        $field->setType($type);
        $field->setMarker($marker);
        $field->setText($text);

        return $field;
    }
}
