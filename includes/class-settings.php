<?php
namespace PrivateMedia;

class Settings {

	/**
	 * Instantiate any WP hooks that need to be fired.
	 */
	public function hooks(){

		// Is Private Checkbox
		add_action( 'attachment_submitbox_misc_actions', array( $this, 'private_attachment_field' ) , 11 );
		add_filter( 'attachment_fields_to_save', array( $this, 'private_attachment_field_save' ), 10, 2 );
		add_filter( 'restrict_manage_posts', array( $this, 'filter_posts_toggle' ) );
		
		// Styles
		add_action( 'admin_head', array( $this, 'post_edit_style' ) );

	}

	/**
	 * Set a reference to the main plugin instance.
	 *
	 * @param $plugin Plugin instance.
	 * @return Database instance
	 */
	public function set_plugin( $plugin ) {

		$this->plugin = $plugin;
		return $this;

	}

	/**
	 * Add 'Make file private' checkbox to edit attachment screen
	 *
	 * Adds the setting field to the submit box.
	 */
	function private_attachment_field() {

		$is_private = $this->is_attachment_private( get_the_id() );

		?>
		<div class="misc-pub-section">
			<label for="mphpf2"><input type="checkbox" id="mphpf2" name="<?php echo $this->prefix; ?>_is_private" <?php checked( $is_private, true ); ?> style="margin-right: 5px;"/>
			Make this file private</label>
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
	 */
	function private_attachment_field_save( $post, $attachment ) {

		$uploads = wp_upload_dir();
		$creds   = request_filesystem_credentials( add_query_arg( null, null ) );

		$this->mphpf_get_private_dir( true );

		if ( ! $creds ) {
			// Handle Error.
			// We can't actually display the form here because this is a filter and the page redirects and it will not be shown.
			$message = '<strong>Private Media Error</strong> WordPress is not able to write files';
			$this->admin_notices->add_notice( $message, false, 'error' );
			return $post;
		}

		if ( $creds && WP_Filesystem( $creds ) ) {

			global $wp_filesystem;

			$make_private = isset( $_POST[$this->prefix .'_is_private'] ) && 'on' == $_POST[$this->prefix .'_is_private'];

			$new_location = null;

			if ( $make_private ) {

				$old_location = get_post_meta( $post['ID'], '_wp_attached_file', true );
				if ( $old_location && false === strpos( $old_location, 'private-files-' . MPHPF_KEY ) )
					$new_location = 'private-files-' . MPHPF_KEY . '/' . $old_location;

			} else {

				// Update location of file in meta.
				$old_location = get_post_meta( $post['ID'], '_wp_attached_file', true );
				if ( $old_location && false !== strpos( $old_location, 'private-files-' . MPHPF_KEY ) )
					$new_location = str_replace( 'private-files-' . MPHPF_KEY . '/', '', $old_location );

			}

			$metadata = get_post_meta( $post['ID'], '_wp_attachment_metadata', true );

			if ( ! $new_location )
				return $post;

			$old_path = trailingslashit( $uploads['basedir'] ) . $old_location;
			$new_path = trailingslashit( $uploads['basedir'] ) . $new_location;

			// Create destination
			if ( ! is_dir( dirname( $new_path ) ) )
				wp_mkdir_p( dirname( $new_path ) );

			$move = $wp_filesystem->move( $old_path, $new_path );

			if ( isset( $metadata['sizes'] ) )
				foreach ( $metadata['sizes'] as $key => $size ) {
					$old_image_size_path = trailingslashit( dirname( $old_path ) ) . $size['file'];
					$new_image_size_path = trailingslashit( dirname( $new_path ) ) . $size['file'];
					$move = $wp_filesystem->move( $old_image_size_path, $new_image_size_path );
				}


			if ( ! $move ) {
				// @todo handle errors.
			}

			if ( $make_private )
				update_post_meta( $post['ID'], 'mphpf_is_private', true );
			else
				delete_post_meta( $post['ID'], 'mphpf_is_private' );

			update_post_meta( $post['ID'], '_wp_attached_file', $new_location );

			$metadata['file' ] = $new_location;
			update_post_meta( $post['ID'], '_wp_attachment_metadata', $metadata );

		}

		return $post;

	}

	/**
	 * Output select field for filtering media by public/private.
	 *
	 * @return null
	 */
	function filter_posts_toggle() {

		$is_private_filter_on = isset( $_GET['private_posts'] ) && 'private' == $_GET['private_posts'];

		?>

		<select name="private_posts">
			<option <?php selected( $is_private_filter_on, false ); ?> value="public">Public</option>
			<option <?php selected( $is_private_filter_on, true ); ?> value="private">Private</option>
		</select>

		<?php

	}

	/**
	 * Insert CSS into admin head on edit attachment screen fro private files.
	 *
	 * @return null
	 */
	function post_edit_style() {

		$icon_url = trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/icon_lock.png';
		$icon_url_2x = trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/icon_lock@2x.png';

		if ( is_admin() && 'attachment' == get_current_screen()->id && $this->is_attachment_private( get_the_id() ) ) : ?>

			<style>
				#titlediv { padding-left: 60px; }
				#titlediv::before { content: ' '; display: block; height: 26px; width: 21px; background: url(<?php echo $icon_url; ?>) no-repeat center center; position: relative; float: left; margin-left: -40px; top: 4px; }
				@media only screen and ( -webkit-min-device-pixel-ratio : 1.5 ), only screen and ( min-device-pixel-ratio : 1.5 ) {
					#titlediv::before { background-image: url(<?php echo $icon_url_2x; ?>); }
				}
			</style>

		<?php endif;

	}
}
