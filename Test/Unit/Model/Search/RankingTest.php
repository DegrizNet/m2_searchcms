<?php
declare(strict_types=1);

namespace Magelan\SearchCms\Test\Unit\Model\Search;

use Magelan\SearchCms\Model\Search\ExclusionMatcher;
use Magelan\SearchCms\Model\Search\Ranking;
use Magelan\SearchCms\Model\Config;
use PHPUnit\Framework\TestCase;

class RankingTest extends TestCase
{
    public function testTitleBeatsText(): void
    {
        $ranking = new Ranking();
        $titlePage = ['title_f' => 'dostava in placilo', 'heading_f' => '', 'meta_f' => '', 'body_f' => 'cene'];
        $textPage = ['title_f' => 'o nas', 'heading_f' => '', 'meta_f' => '', 'body_f' => 'dostava dostava dostava'];
        self::assertGreaterThan(
            $ranking->score($textPage, ['dostava'])['score'],
            $ranking->score($titlePage, ['dostava'])['score']
        );
    }

    public function testWeightsAndCap(): void
    {
        $ranking = new Ranking();
        $row = [
            'title_f' => 'dostava',
            'heading_f' => 'dostava po sloveniji',
            'meta_f' => 'dostava',
            'body_f' => str_repeat('dostava ', 30),
        ];
        $result = $ranking->score($row, ['dostava'], 'dostava');
        self::assertSame(
            Ranking::TITLE_EXACT + Ranking::TITLE + Ranking::HEADING + Ranking::META + Ranking::BODY * Ranking::BODY_CAP,
            $result['score']
        );
        self::assertSame(1, $result['matched']);
    }

    public function testMatchedCountsTermsAnywhere(): void
    {
        $ranking = new Ranking();
        $row = ['title_f' => 'vracilo', 'heading_f' => '', 'meta_f' => '', 'body_f' => 'rok 14 dni'];
        self::assertSame(2, $ranking->score($row, ['vracilo', 'rok', 'garancija'])['matched']);
    }

    public function testRequiredInFallback(): void
    {
        $ranking = new Ranking();
        self::assertSame(1, $ranking->requiredInFallback(1));
        self::assertSame(2, $ranking->requiredInFallback(2));
        self::assertSame(2, $ranking->requiredInFallback(3));
        self::assertSame(2, $ranking->requiredInFallback(4));
        self::assertSame(3, $ranking->requiredInFallback(5));
    }

    public function testExclusions(): void
    {
        $matcher = new ExclusionMatcher();
        $list = Config::parseIdentifierList("no-route\nhome\r\ncookie*\n\n  privacy-policy-cookie-restriction-mode  ");
        self::assertSame(['no-route', 'home', 'cookie*', 'privacy-policy-cookie-restriction-mode'], $list);
        self::assertTrue($matcher->isExcluded('home', $list));
        self::assertTrue($matcher->isExcluded('HOME', $list));
        self::assertTrue($matcher->isExcluded('cookie-settings', $list));
        self::assertTrue($matcher->isExcluded('privacy-policy-cookie-restriction-mode', $list));
        self::assertFalse($matcher->isExcluded('homepage-promo', $list));
        self::assertFalse($matcher->isExcluded('dostava', $list));
        self::assertFalse($matcher->isExcluded('dostava', []));
    }
}
