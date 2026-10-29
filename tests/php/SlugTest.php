<?php
/**
 * Bulgarian names become Latin slugs.
 */

use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase {

	public function test_bulgarian_letters_in_the_streamlined_system(): void {
		$this->assertSame( 'termo chasha camp', Reklamo_I18n::transliterate( 'Термо чаша Camp' ) );
		$this->assertSame( 'biznes komplekt magnet', Reklamo_I18n::transliterate( 'Бизнес комплект Magnet' ) );
		$this->assertSame( 'korporativni podaratsi', Reklamo_I18n::transliterate( 'Корпоративни подаръци' ) );
		$this->assertSame( 'shtarkel yuzhen yaz zhaba', Reklamo_I18n::transliterate( 'Щъркел Южен Яз Жаба' ) );
	}

	public function test_only_when_saving_and_only_cyrillic(): void {
		$this->assertSame( 'metalna himikalka', Reklamo_I18n::latin_slug( 'Метална химикалка', '', 'save' ) );
		$this->assertSame( 'Метална химикалка', Reklamo_I18n::latin_slug( 'Метална химикалка', '', 'query' ) );
		$this->assertSame( 'Pen Duo', Reklamo_I18n::latin_slug( 'Pen Duo', '', 'save' ) );
	}
}
