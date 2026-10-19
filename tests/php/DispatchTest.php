<?php
/**
 * The waybill number the shop types, and the courier tracking link built from it.
 */

use PHPUnit\Framework\TestCase;

final class DispatchTest extends TestCase {

	public function test_waybill_is_tidied(): void {
		$this->assertSame( '1051234567890', Reklamo_Dispatch::clean_waybill( ' 1051234567890 ' ) );
		$this->assertSame( 'PG-12 345', Reklamo_Dispatch::clean_waybill( 'pg-12   345' ) );
		$this->assertSame( '', Reklamo_Dispatch::clean_waybill( '   ' ) );
	}

	public function test_waybill_refuses_anything_else(): void {
		foreach ( array( '12', '<script>', '123/456', str_repeat( '9', 41 ), '-1234' ) as $bad ) {
			$this->assertNull( Reklamo_Dispatch::clean_waybill( $bad ), $bad );
		}
	}

	public function test_tracking_links_per_courier(): void {
		$this->assertSame( 'https://www.econt.com/services/track-shipment/1051234567890', Reklamo_Dispatch::tracking_url( 'econt', '1051234567890' ) );
		$this->assertSame( 'https://www.speedy.bg/bg/track-shipment?shipmentNumber=60012345678', Reklamo_Dispatch::tracking_url( 'speedy', '600 123-456 78' ) );
		$this->assertSame( 'https://boxnow.bg/?track=9123456789', Reklamo_Dispatch::tracking_url( 'boxnow', '9123456789' ) );
	}

	public function test_no_link_without_a_tracking_page_or_a_number(): void {
		$this->assertSame( '', Reklamo_Dispatch::tracking_url( 'pigeon', 'PG123456' ) );
		$this->assertSame( '', Reklamo_Dispatch::tracking_url( '', '1051234567890' ) );
		$this->assertSame( '', Reklamo_Dispatch::tracking_url( 'econt', '' ) );
	}
}
