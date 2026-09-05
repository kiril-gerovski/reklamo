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
			'bank'    => __( 'Bank details', 'reklamo-core' ),
			'process' => __( 'Process', 'reklamo-core' ),
			'files'   => __( 'Files', 'reklamo-core' ),
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

	protected function get_settings_for_bank_section() {
		return array(
			array(
				'type'  => 'title',
				'id'    => 'reklamo_bank',
				'title' => __( 'Bank details', 'reklamo-core' ),
				'desc'  => __( 'Printed in the deposit and final-payment emails and by the [reklamo_bank_details] shortcode. The order number is always the payment reference.', 'reklamo-core' ),
			),
			array(
				'id'    => 'reklamo_bank_name',
				'title' => __( 'Bank', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_iban',
				'title' => __( 'IBAN', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_bic',
				'title' => __( 'BIC / SWIFT', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'reklamo_account_holder',
				'title' => __( 'Account holder', 'reklamo-core' ),
				'type'  => 'text',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'reklamo_bank',
			),
		);
	}

	protected function get_settings_for_files_section() {
		return array(
			array(
				'type'  => 'title',
				'id'    => 'reklamo_files',
				'title' => __( 'Customer files', 'reklamo-core' ),
				'desc'  => __( 'Logos are uploaded in small chunks, so the server\'s PHP upload limit does not apply — only this maximum does. Check WooCommerce → Reklamo diagnostics after deploying.', 'reklamo-core' ),
			),
			array(
				'id'                => 'reklamo_max_upload_mb',
				'title'             => __( 'Maximum logo file size (MB)', 'reklamo-core' ),
				'type'              => 'number',
				'default'           => '300',
				'custom_attributes' => array( 'min' => 1 ),
			),
			array(
				'id'                => 'reklamo_retention_months',
				'title'             => __( 'Delete files after (months)', 'reklamo-core' ),
				'type'              => 'number',
				'default'           => '12',
				'description'       => __( 'Logos and mockups of completed or cancelled orders are deleted this many months after the order closed. 0 = keep forever. State the period in your terms.', 'reklamo-core' ),
				'desc_tip'          => true,
				'custom_attributes' => array( 'min' => 0 ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'reklamo_files',
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
				'id'          => 'reklamo_reminder_days',
				'title'       => __( 'Reminders (days)', 'reklamo-core' ),
				'type'        => 'text',
				'default'     => '3,7,14',
				'description' => __( 'Days after a mockup is sent (or a deposit requested) to remind the customer, comma-separated. Empty = no reminders.', 'reklamo-core' ),
				'desc_tip'    => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'reklamo_process',
			),
		);
	}
}
