<?php
declare(strict_types=1);

namespace Magelan\SearchCms\Test\Unit\Model\Text;

use Magelan\SearchCms\Model\Text\HtmlToText;
use PHPUnit\Framework\TestCase;

class HtmlToTextTest extends TestCase
{
    private HtmlToText $converter;

    protected function setUp(): void
    {
        $this->converter = new HtmlToText();
    }

    public function testRemovesDirectivesScriptsAndTags(): void
    {
        $html = '<div class="block"><h2>Dostava</h2><p>Pošljemo v 1-3 dneh.</p>'
            . '{{widget type="Magento\\Cms\\Block\\Widget\\Block" template="widget/static_block/default.phtml" block_id="5"}}'
            . '<script>var div = "class";</script><style>.x{color:red}</style><!-- skrito -->'
            . '<p>Plačilo&nbsp;po povzetju &amp; kartice</p></div>';
        self::assertSame('Dostava Pošljemo v 1-3 dneh. Plačilo po povzetju & kartice', $this->converter->convert($html));
    }

    public function testBlockTagsSeparateWords(): void
    {
        self::assertSame('Dostava Plačilo', $this->converter->convert('<p>Dostava</p><p>Plačilo</p>'));
        self::assertSame('ena dva', $this->converter->convert('ena<br>dva'));
    }

    public function testPageBuilderMarkupAndEscapedHtmlCode(): void
    {
        $html = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-element="inner" style="justify-content: flex-start;">'
            . '<div data-content-type="text" data-appearance="default" data-element="main"><p>Vračilo v 14 dneh</p></div>'
            . '<div data-content-type="html" data-appearance="default" data-element="main">&lt;p&gt;Garancija&lt;/p&gt;&lt;script&gt;x()&lt;/script&gt;</div>'
            . '</div></div>';
        self::assertSame('Vračilo v 14 dneh Garancija', $this->converter->convert($html));
    }

    public function testEmptyAndLength(): void
    {
        self::assertSame('', $this->converter->convert(null));
        self::assertSame('', $this->converter->convert('  <p> </p> '));
        self::assertSame('abcde', $this->converter->convert('<p>abcdefgh</p>', 5));
    }
}
