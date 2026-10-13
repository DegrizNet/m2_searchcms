<?php
declare(strict_types=1);

namespace Magelan\SearchCms\Test\Unit\Model\Search;

use Magelan\SearchCms\Model\Search\QueryParser;
use Magelan\SearchCms\Model\Text\Folder;
use PHPUnit\Framework\TestCase;

class QueryParserTest extends TestCase
{
    private QueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QueryParser(new Folder());
    }

    public function testExtraSpacesNeverProduceAnEmptyTerm(): void
    {
        self::assertSame(['dostav'], $this->parser->parse('dostava '));
        self::assertSame(['dostav', 'cena'], $this->parser->parse('  dostava   cena '));
        self::assertSame([], $this->parser->parse('   '));
        self::assertSame([], $this->parser->parse('!!! ---'));
    }

    public function testFoldsAndSplitsOnPunctuation(): void
    {
        self::assertSame(['vracil', 'reklamacij'], $this->parser->parse('Vračilo/reklamacija'));
        self::assertSame(['hp', '652'], $this->parser->parse('HP-652'));
    }

    public function testStopWordsAndShortTermsAreDropped(): void
    {
        self::assertSame(['dostav', 'tujin'], $this->parser->parse('dostava v tujino'));
        self::assertSame(['shipping', 'returns'], $this->parser->parse('shipping and returns'));
        // the whole query is stop words: search for them after all
        self::assertSame(['za'], $this->parser->parse('za'));
        // single characters alone are never searched
        self::assertSame([], $this->parser->parse('a v'));
    }

    public function testDuplicatesLimitsAndPhrase(): void
    {
        self::assertSame(['dostav'], $this->parser->parse('dostava Dostava DOSTAVA'));
        self::assertCount(QueryParser::MAX_TERMS, $this->parser->parse('aa bb cc dd ee ff gg hh ii jj'));
        self::assertSame([], $this->parser->parse(str_repeat('x', 65)));
        self::assertSame('splosni pogoji', $this->parser->phrase('  Splošni   pogoji '));
    }

    public function testLikeMetaCharactersStayLiteral(): void
    {
        // "%" and "_" are separators here, so they can never reach the LIKE pattern as wildcards.
        self::assertSame(['50', 'popust'], $this->parser->parse('50% popust'));
        self::assertSame(['snak', 'case'], $this->parser->parse('snake_case'));
    }

    public function testLightStemming(): void
    {
        self::assertSame('dostav', $this->parser->stem('dostava'));
        self::assertSame('pogoj', $this->parser->stem('pogoji'));
        self::assertSame('yoga', $this->parser->stem('yoga'));
        self::assertSame('delivery', $this->parser->stem('delivery'));
        self::assertSame(['pogoj'], $this->parser->parse('pogoji Pogoji'));
    }
}
