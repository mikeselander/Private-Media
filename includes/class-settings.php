<?php
/**
 * Enable and save settings for the plugin.
 *
 * @package WordPress
 * @subpackage Private Media
 */

namespace PrivateMedia;

class Settings {

	/**
	 * Hash used for private media folder.
	 *
	 * @var string
	 */
	private $hash;

	/**
	 * Holder for Utilities class.
	 *
	 * @var Utilities
	 */
	private $utilities;

	/**
	 * Holder for Rewrites class.
	 *
	 * @var Rewrites
	 */
	private $rewrites;

	/**
	 * Plugin definitions.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Plugin slug used for settings.
	 *
	 * @var string
	 */
	private $slug;

	public function __construct() {
		
		$this->hash      = Utilities::get_hash();
		$this->utilities = new Utilities;
		$this->rewrites  = new Rewrites;
	}

	/**
	 * Instantiate any WP hooks that need to be fired.
	 */
	public function hooks() {

		// Is Private Checkbox.
		add_action( 'attachment_submitbox_misc_actions', array( $this, 'private_attachment_field' ) , 11 );
		add_filter( 'attachment_fields_to_save', array( $this, 'private_attachment_field_save' ), 10, 2 );

		// Styles.
		add_action( 'admin_head', array( $this, 'post_edit_style' ) );
	}

	/**
	 * Set a reference to the main plugin instance.
	 *
	 * @param object $plugin Plugin instance.
	 * @return Settings instance
	 */
	public function set_plugin( $plugin ) {

		$this->plugin = $plugin;
		$this->slug   = $this->plugin->definitions->slug;

		return $this;
	}

	/**
	 * Add 'Make file private' checkbox to edit attachment screen
	 *
	 * Adds the setting field to the submit box.
	 */
	function private_attachment_field() {

		$is_private = $this->utilities->is_attachment_private( get_the_id() );

		?>
		<div class="misc-pub-section">
			<label for="<?php echo esc_attr( $this->slug ); ?>"><input type="checkbox" id="<?php echo esc_attr( $this->slug ); ?>" name="<?php echo esc_attr( $this->slug ); ?>_is_private" <?php checked( $is_private, true ); ?> style="margin-right: 5px;"/>
			<?php esc_html_e( 'Make this file private', 'private-media' ); ?></label>
		</div>
		<?php
	}

	/**
	 * Save private attachment field settings.
	 *
	 * On save - update settings and move files.
	 * Uses WP_Filesystem
	 *
	 * @todo check this out. Might need to handle edge cases.
	 * @param object $post Post object.
	 * @param int    $attachment Attachment ID.
	 * @return object Post object.
	 */
	function private_attachment_field_save( $post, $attachment ) {

		$uploads = wp_upload_dir();
		$creds   = request_filesystem_credentials( add_query_arg( null, null ) );

		$this->rewrites->get_private_dir( true );

		if ( ! $creds ) {
			// Handle Error.
			// We can't actually display the form here because this is a filter and the page redirects and it will not be shown.
			$message = __( '<strong>Private Media Error</strong> WordPress is not able to write files', 'private-media' );
			$this->admin_notices->add_notice( $message, false, 'error' );
			return $post;
		}

		if ( $creds && WP_Filesystem( $creds ) ) {

			global $wp_filesystem;

			$make_private = isset( $_POST[ $this->slug .'_is_private' ] ) && 'on' == $_POST[ $this->slug .'_is_private' ];

			$new_location = null;

			if ( $make_private ) {

				$old_location = get_post_meta( $post['ID'], '_wp_attached_file', true );
				if ( $old_location && false === strpos( $old_location, 'private-files-' . $this->hash ) ) {
					$new_location = 'private-files-' . $this->hash . '/' . $old_location;
				}
			} else {

				// Update location of file in meta.
				$old_location = get_post_meta( $post['ID'], '_wp_attached_file', true );
				if ( $old_location && false !== strpos( $old_location, 'private-files-' . $this->hash ) ) {
					$new_location = str_replace( 'private-files-' . $this->hash . '/', '', $old_location );
				}
			}

			$metadata = get_post_meta( $post['ID'], '_wp_attachment_metadata', true );

			if ( ! $new_location ) {
				return $post;
			}

			$old_path = trailingslashit( $uploads['basedir'] ) . $old_location;
			$new_path = trailingslashit( $uploads['basedir'] ) . $new_location;

			// Create destination.
			if ( ! is_dir( dirname( $new_path ) ) ) {
				wp_mkdir_p( dirname( $new_path ) );
			}

			$move = $wp_filesystem->move( $old_path, $new_path );

			if ( isset( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $key => $size ) {
					$old_image_size_path = trailingslashit( dirname( $old_path ) ) . $size['file'];
					$new_image_size_path = trailingslashit( dirname( $new_path ) ) . $size['file'];
					$move = $wp_filesystem->move( $old_image_size_path, $new_image_size_path );
				}
			}

			if ( $make_private ) {
				update_post_meta( $post['ID'], $this->slug . '_is_private', true );
			} else {
				delete_post_meta( $post['ID'], $this->slug . '_is_private' );
			}

			update_post_meta( $post['ID'], '_wp_attached_file', $new_location );

			$metadata['file'] = $new_location;
			update_post_meta( $post['ID'], '_wp_attachment_metadata', $metadata );

		}

		return $post;
	}

	/**
	 * Insert CSS into admin head on edit attachment screen fro private files.
	 */
	function post_edit_style() {

		// Locked
		if ( $this->utilities->is_attachment_private( get_the_id() ) ) {
			$lock = '\f160';
		} else {
			$lock = '\f528';
		}

		if ( is_admin() && 'attachment' == get_current_screen()->id ) : ?>

			<style>
				#titlediv { padding-left: 60px; }
				#titlediv::before { content: "<?php echo esc_attr( $lock ); ?>"; font-family: dashicons; font-size: 4em; line-height: 1; display: block; position: relative; float: left; margin-left: -60px; top: 8px; }
			</style>

		<?php endif;
	}
}
