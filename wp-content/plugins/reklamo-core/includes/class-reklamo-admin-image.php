<?php
/**
 * An image picker for admin forms: a hidden input with the attachment ID, a preview and the
 * Media Library. Used by Settings → Reklamo and by the product editor.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Admin_Image {

	/**
	 * @param string $name  Input name and id.
	 * @param int    $value Attachment ID, 0 for none.
	 * @param string $desc  Help text under the buttons.
	 */
	public static function render( string $name, int $value, string $desc = '' ): void {
		wp_enqueue_media();
		wp_enqueue_script( 'reklamo-admin-image', REKLAMO_URL . 'assets/js/admin-image.js', array(), REKLAMO_VERSION, true );
		$preview = $value ? wp_get_attachment_image( $value, array( 96, 96 ) ) : '';
		?>
		<div class="reklamo-image-field" data-reklamo-image>
			<input type="hidden" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ? (string) $value : '' ); ?>">
			<div data-reklamo-image-preview style="margin-bottom:8px"><?php echo wp_kses_post( $preview ); ?></div>
			<button type="button" class="button" data-reklamo-image-pick><?php esc_html_e( 'Choose image', 'reklamo-core' ); ?></button>
			<button type="button" class="button-link-delete" data-reklamo-image-clear <?php echo $value ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'reklamo-core' ); ?></button>
			<?php if ( '' !== $desc ) : ?>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
