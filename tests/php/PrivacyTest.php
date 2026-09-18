<?php
/**
 * Pure parts of the personal-data handling: IP masking in order notes and the list of
 * meta keys anonymisation removes.
 */

use PHPUnit\Framework\TestCase;

final class PrivacyTest extends TestCase {

	public function test_ipv4_is_masked_inside_a_note(): void {
		$this->assertSame(
			'Customer approved mockup #2 (IP [ip]).',
			Reklamo_Privacy::mask_ips( 'Customer approved mockup #2 (IP 185.80.2.76).' )
		);
	}

	public function test_ipv6_is_masked(): void {
		$this->assertSame( 'from [ip] today', Reklamo_Privacy::mask_ips( 'from 2a01:4f8:c010:1a2b::1 today' ) );
		$this->assertSame( '[ip]', Reklamo_Privacy::mask_ips( 'fe80::1ff:fe23:4567:890a' ) );
	}

	public function test_numbers_that_are_not_addresses_stay(): void {
		$this->assertSame( 'Order 66 total 100,00 EUR at 18.09.2026 12:30', Reklamo_Privacy::mask_ips( 'Order 66 total 100,00 EUR at 18.09.2026 12:30' ) );
		$this->assertSame( 'version 1.2.3', Reklamo_Privacy::mask_ips( 'version 1.2.3' ) );
	}

	public function test_personal_meta_covers_every_customer_written_key(): void {
		foreach ( array( '_reklamo_track_url', '_reklamo_change_requests', '_reklamo_last_change_request', '_reklamo_consent_ip', '_reklamo_eik', '_reklamo_vat', '_reklamo_mol', '_reklamo_delivery_note' ) as $key ) {
			$this->assertContains( $key, Reklamo_Privacy::PERSONAL_META );
		}
		foreach ( array( '_reklamo_consent_at', '_reklamo_consent_version', '_reklamo_waiver_at', '_reklamo_approved_at', '_reklamo_deposit_amount' ) as $keep ) {
			$this->assertNotContains( $keep, Reklamo_Privacy::PERSONAL_META, "$keep is proof of the contract and must survive anonymisation" );
		}
	}

	public function test_closed_statuses(): void {
		$this->assertSame( array( 'completed', 'cancelled', 'refunded' ), Reklamo_Privacy::CLOSED );
	}
}
