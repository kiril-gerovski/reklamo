<?php
/**
 * WooCommerce → Settings → Reklamo.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'reklamo';
		$this->label = 'Reklamo';
		parent::__construct();
	}

	protected function get_own_sections() {
		return array(
			''        => __( 'Company & contact', 'reklamo-core' ),
			'process' => __( 'Process', 'reklamo-core' ),
		);
	}

	protected function get_settings_for_default_section() {
		return array(
			array(
				'type'  => 'title',
				'id'    => 'reklamo_company',
				'title' => __( 'Company & contact', 'reklamo-core' ),
				'desc'  => __( 'Shown in the footer, the contact blocks and the emails. Edit here once; every page updates.', 'reklamo-core' ),
			),
			array(
				'id'    => 'reklamo_company_name',
				'title' => __( 'Company name', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_tagline',
				'title' => __( 'Footer tagline', 'reklamo-core' ),
				'type'  => 'textarea',
				'css'   => 'width:400px;height:70px;',
			),
			array(
				'id'    => 'reklamo_phone',
				'title' => __( 'Phone', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_email',
				'title' => __( 'Contact email', 'reklamo-core' ),
				'type'  => 'email',
			),
			array(
				'id'    => 'reklamo_address',
				'title' => __( 'Address / city', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_hours',
				'title' => __( 'Working hours', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_facebook',
				'title' => __( 'Facebook URL', 'reklamo-core' ),
				'type'  => 'url',
			),
			array(
				'id'    => 'reklamo_instagram',
				'title' => __( 'Instagram URL', 'reklamo-core' ),
				'type'  => 'url',
			),
			array(
				'id'    => 'reklamo_linkedin',
				'title' => __( 'LinkedIn URL', 'reklamo-core' ),
				'type'  => 'url',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'reklamo_company',
			),
		);
	}

	protected function get_settings_for_process_section() {
		return array(
			array(
				'type'  => 'title',
				'id'    => 'reklamo_process',
				'title' => __( 'Process promises', 'reklamo-core' ),
			),
			array(
				'id'                => 'reklamo_mockup_deadline',
				'title'             => __( 'Mockup within (business hours)', 'reklamo-core' ),
				'type'              => 'number',
				'default'           => '24',
				'custom_attributes' => array( 'min' => 1 ),
			),
			array(
				'id'                => 'reklamo_deposit_pct',
				'title'             => __( 'Deposit (%)', 'reklamo-core' ),
				'type'              => 'number',
				'default'           => '50',
				'custom_attributes' => array(
					'min' => 0,
					'max' => 100,
				),
			),
			array(
				'id'                => 'reklamo_note_max',
				'title'             => __( 'Designer note max characters', 'reklamo-core' ),
				'type'              => 'number',
				'default'           => '300',
				'custom_attributes' => array( 'min' => 50 ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'reklamo_process',
			),
		);
	}
}
