<?php
namespace PrivateMedia;

class Utilities {

	/**
	 * Instantiate any WP hooks that need to be fired.
	 */
	public function hooks(){



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
	 * Check if attachment is private
	 *
	 * @param  int $attachment_id
	 * @return boolean
	 */
	function is_attachment_private( $attachment_id ) {

		return get_post_meta( $attachment_id, 'mphpf_is_private', true );

	}

	/**
	 * Check if current user can view attachment
	 *
	 * @todo  allow this to be filtered for more advanced use.
	 *
	 * @param  int $attachment_id
	 * @param  int $user_id (if not passed, assumed current user)
	 * @return boolean
	 */
	function can_user_view( $attachment_id, $user_id = null ) {

		$user_id = ( $user_id ) ? $user_id : get_current_user_id();

		if ( ! $attachment_id )
			return false;

		$private_status = $this->is_attachment_private( $attachment_id );

		if ( ! empty( $private_status ) && ! is_user_logged_in() )
			return false;

		return true;

	}

	/**
	 * Output link to file if user is logged in.
	 * Else output a message.
	 *
	 * @param  array $atts shortcode attributes
	 * @return string shortcode output.
	 */
	function get_private_url($atts) {

		if ( $this->is_attachment_private( $atts['id'] ) && ! is_user_logged_in() )
			$link = 'You must be logged in to access this file.';
		elseif ( isset( $atts['attachment_page'] ) )
			$link = wp_get_attachment_link( $atts['id'] );
		else
			$link = sprintf( '<a href="%s">%s</a>', esc_url( wp_get_attachment_url( $atts['id'] ) ), esc_html( basename( wp_get_attachment_url( $atts['id'] ) ) ) );

		return $link;

	}

	/**
	 * Get attachment id from attachment name
	 *
	 * @todo  surely this isn't the best way to do this?
	 * @param  [type] $attachment [description]
	 * @return [type]             [description]
	 */
	function get_attachment_id_from_name( $attachment ) {

		$attachment_post = new WP_Query( array(
			'post_type' => 'attachment',
			'showposts' => 1,
			'post_status' => 'inherit',
			'name' => $attachment,
			'show_private' => true
		) );

		if ( empty( $attachment_post->posts ) )
			return;

		return reset( $attachment_post->posts )->ID;

	}
}
