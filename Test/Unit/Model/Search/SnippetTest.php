<?php
declare(strict_types=1);

namespace Magelan\SearchCms\Test\Unit\Model\Search;

use Magelan\SearchCms\Model\Search\Snippet;
use Magelan\SearchCms\Model\Text\Folder;
use PHPUnit\Framework\TestCase;

class SnippetTest extends TestCase
{
    private Snippet $snippet;

    protected function setUp(): void
    {
        $this->snippet = new Snippet(new Folder());
    }

    public function testMarksTermAtStartAndKeepsOriginalSpelling(): void
    {
        $html = $this->snippet->build('Vračilo je mogoče v 14 dneh.', ['vracilo']);
        self::assertSame('<mark>Vračilo</mark> je mogoče v 14 dneh.', $html);
    }

    public function testEscapesHtmlAndMarksEveryOccurrence(): void
    {
        $html = $this->snippet->build('Dostava <b> & dostava "hitro"', ['dostava']);
        self::assertSame('<mark>Dostava</mark> &lt;b&gt; &amp; <mark>dostava</mark> &quot;hitro&quot;', $html);
    }

    public function testWindowAroundMatchInTheMiddle(): void
    {
        $text = str_repeat('Lorem ipsum dolor sit amet. ', 20) . 'Brezplačna dostava nad 50 EUR. ' . str_repeat('Konec besedila. ', 20);
        $html = $this->snippet->build($text, ['dostava']);
        self::assertStringStartsWith('… ', $html);
        self::assertStringEndsWith(' …', $html);
        self::assertStringContainsString('Brezplačna <mark>dostava</mark> nad 50 EUR.', $html);
        self::assertLessThanOrEqual(Snippet::LENGTH + 30, mb_strlen(strip_tags($html)));
    }

    public function testMatchAtTheEnd(): void
    {
        $text = str_repeat('besedilo ', 40) . 'in na koncu reklamacija';
        $html = $this->snippet->build($text, ['reklamacija']);
        self::assertStringEndsWith('<mark>reklamacija</mark>', $html);
        self::assertStringStartsWith('… ', $html);
    }

    public function testNoMatchGivesStartOfText(): void
    {
        $text = 'Pošiljamo v Avstrijo, Italijo in na Hrvaško. ' . str_repeat('Več besedila tukaj. ', 15);
        $html = $this->snippet->build($text, ['tujina']);
        self::assertStringStartsWith('Pošiljamo v Avstrijo', $html);
        self::assertStringNotContainsString('<mark>', $html);
        self::assertStringEndsWith(' …', $html);
    }

    public function testMultibyteAndOverlappingTerms(): void
    {
        $html = $this->snippet->build('Größe und Straße', ['grosse', 'strasse', 'ss']);
        self::assertSame('<mark>Größe</mark> und <mark>Straße</mark>', $html);
        self::assertSame('', $this->snippet->build('   ', ['x']));
    }

    public function testWholeWordIsMarkedForAStem(): void
    {
        $html = $this->snippet->build('Izberete lahko dostavo v paketnik.', ['dostav']);
        self::assertSame('Izberete lahko <mark>dostavo</mark> v paketnik.', $html);
    }
}
