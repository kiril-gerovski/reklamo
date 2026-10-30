<?php
/**
 * The owner's own wording for search results: a "In Google" box on pages and products, and the
 * same fields on product categories. Empty fields fall back to what seo.php works out itself.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

const REKLAMO_SEO_FIELDS = array( 'title', 'description', 'text' );

/** Title, description or text below the products for this request; empty when the owner left it blank. */
function reklamo_seo_field( string $field ): string {
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		return 'description' === $field ? '' : (string) get_term_meta( get_queried_object_id(), '_reklamo_seo_' . $field, true );
	}
	$post_id = 0;
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$post_id = (int) wc_get_page_id( 'shop' );
	} elseif ( is_singular() ) {
		$post_id = (int) get_queried_object_id();
	}
	return $post_id > 0 ? trim( (string) get_post_meta( $post_id, '_reklamo_seo_' . $field, true ) ) : '';
}

/** The text below the products, on a category, Промо пакети, Продукти and Корпоративни подаръци. */
function reklamo_seo_text_below(): void {
	$text = reklamo_seo_field( 'text' );
	if ( '' === $text ) {
		return;
	}
	echo '<section class="shop-about">' . wp_kses_post( wpautop( $text ) ) . '</section>';
}

/** Field labels and hints, shared by the post box and the category form. */
function reklamo_seo_field_labels(): array {
	return array(
		'title'       => array( __( 'Title in Google', 'reklamo' ), __( 'Up to about 60 characters, the words people search for first. Empty: worked out automatically.', 'reklamo' ) ),
		'description' => array( __( 'Description in Google', 'reklamo' ), __( 'Up to about 155 characters, shown under the title in the results. Empty: worked out automatically.', 'reklamo' ) ),
		'text'        => array( __( 'Text below the products', 'reklamo' ), __( 'A few paragraphs about this range for customers and Google. Shown only on pages that list products.', 'reklamo' ) ),
	);
}

/** One labelled field with its hint. */
function reklamo_seo_field_html( string $field, string $value ): void {
	$labels = reklamo_seo_field_labels();
	$name   = 'reklamo_seo[' . $field . ']';
	$id     = 'reklamo-seo-' . $field;
	printf( '<p><label for="%s"><strong>%s</strong></label><br>', esc_attr( $id ), esc_html( $labels[ $field ][0] ) );
	if ( 'title' === $field ) {
		printf( '<input type="text" class="widefat" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
	} else {
		printf( '<textarea class="widefat" rows="%d" id="%s" name="%s">%s</textarea>', 'text' === $field ? 8 : 3, esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
	}
	printf( '<span class="description">%s</span></p>', esc_html( $labels[ $field ][1] ) );
}

/** Sanitised values from the form, keyed by field. */
function reklamo_seo_posted( array $fields ): ?array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the callers.
	if ( ! isset( $_POST['reklamo_seo'] ) || ! is_array( $_POST['reklamo_seo'] ) ) {
		return null;
	}
	$raw = wp_unslash( $_POST['reklamo_seo'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked by the callers; sanitised per field below.
	$out = array();
	foreach ( $fields as $field ) {
		$value         = (string) ( $raw[ $field ] ?? '' );
		$out[ $field ] = 'text' === $field ? wp_kses_post( $value ) : sanitize_text_field( $value );
	}
	return $out;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		foreach ( array( 'page', 'product' ) as $type ) {
			add_meta_box(
				'reklamo-seo',
				__( 'In Google', 'reklamo' ),
				static function ( WP_Post $post ): void {
					wp_nonce_field( 'reklamo_seo_post', 'reklamo_seo_nonce' );
					$fields = 'page' === $post->post_type ? REKLAMO_SEO_FIELDS : array( 'title', 'description' );
					foreach ( $fields as $field ) {
						reklamo_seo_field_html( $field, (string) get_post_meta( $post->ID, '_reklamo_seo_' . $field, true ) );
					}
				},
				$type,
				'normal',
				'low'
			);
		}
	}
);

add_action(
	'save_post',
	static function ( int $post_id, WP_Post $post ): void {
		if ( ! in_array( $post->post_type, array( 'page', 'product' ), true ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['reklamo_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['reklamo_seo_nonce'] ), 'reklamo_seo_post' ) ) {
			return;
		}
		$values = reklamo_seo_posted( 'page' === $post->post_type ? REKLAMO_SEO_FIELDS : array( 'title', 'description' ) );
		foreach ( (array) $values as $field => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, '_reklamo_seo_' . $field );
			} else {
				update_post_meta( $post_id, '_reklamo_seo_' . $field, $value );
			}
		}
	},
	10,
	2
);

// A category's own description already serves as its description in Google.
add_action(
	'product_cat_add_form_fields',
	static function (): void {
		wp_nonce_field( 'reklamo_seo_term', 'reklamo_seo_nonce' );
		echo '<div class="form-field">';
		reklamo_seo_field_html( 'title', '' );
		reklamo_seo_field_html( 'text', '' );
		echo '</div>';
	}
);

add_action(
	'product_cat_edit_form_fields',
	static function ( WP_Term $term ): void {
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'In Google', 'reklamo' ) . '</th><td>';
		wp_nonce_field( 'reklamo_seo_term', 'reklamo_seo_nonce' );
		reklamo_seo_field_html( 'title', (string) get_term_meta( $term->term_id, '_reklamo_seo_title', true ) );
		reklamo_seo_field_html( 'text', (string) get_term_meta( $term->term_id, '_reklamo_seo_text', true ) );
		echo '</td></tr>';
	}
);

$reklamo_seo_save_term = static function ( int $term_id ): void {
	if ( ! current_user_can( get_taxonomy( 'product_cat' )->cap->edit_terms ) || ! isset( $_POST['reklamo_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['reklamo_seo_nonce'] ), 'reklamo_seo_term' ) ) {
		return;
	}
	foreach ( (array) reklamo_seo_posted( array( 'title', 'text' ) ) as $field => $value ) {
		if ( '' === $value ) {
			delete_term_meta( $term_id, '_reklamo_seo_' . $field );
		} else {
			update_term_meta( $term_id, '_reklamo_seo_' . $field, $value );
		}
	}
};
add_action( 'created_product_cat', $reklamo_seo_save_term );
add_action( 'edited_product_cat', $reklamo_seo_save_term );
