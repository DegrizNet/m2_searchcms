<?php
declare(strict_types=1);

namespace Magelan\SearchCms\Test\Unit\Model\Text;

use Magelan\SearchCms\Model\Text\Folder;
use PHPUnit\Framework\TestCase;

class FolderTest extends TestCase
{
    public function testFoldsDiacriticsAndCase(): void
    {
        $folder = new Folder();
        self::assertSame('vracilo in reklamacija', $folder->fold('Vračilo in REKLAMACIJA'));
        self::assertSame('placilo zasebnost', $folder->fold('Plačilo ZASEBNOST'));
        self::assertSame('grosse fur kaufer', $folder->fold('Größe für Käufer'));
        self::assertSame('dakovo dostava', $folder->fold('Đakovo dostava'));
        self::assertSame('ascii only', $folder->fold('ASCII only'));
        self::assertSame('', $folder->fold(''));
    }

    public function testMapPointsBackToOriginalCharacters(): void
    {
        $folder = new Folder();
        [$folded, $map, $chars] = $folder->foldWithMap('Straße Č');
        self::assertSame('strasse c', $folded);
        // "ss" comes from the 5th character (ß)
        self::assertSame(4, $map[4]);
        self::assertSame(4, $map[5]);
        self::assertSame('ß', $chars[$map[5]]);
        self::assertSame('Č', $chars[$map[strlen($folded) - 1]]);
    }
}
