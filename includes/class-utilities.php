<?php
/**
 * Various utilities used throughout the plugin.
 *
 * @package WordPress
 * @subpackage Private Media
 */

namespace PrivateMedia;

class Utilities {

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
		$this->slug = plugin()->get_definitions()->slug;
	}

	/**
	 * Set and retrieve the private folder hash.
	 *
	 * @return string Hash for private file folder.
	 */
	public static function get_hash() {
		return hash( 'md5', AUTH_KEY );
	}

	/**
	 * Check if attachment is private
	 *
	 * @param  int $attachment_id Attachment ID.
	 * @return boolean
	 */
	public function is_attachment_private( $attachment_id ) {

		return get_post_meta( $attachment_id, $this->slug . '_is_private', true );

	}

	/**
	 * Check if current user can view attachment
	 *
	 * @todo  allow this to be filtered for more advanced use.
	 *
	 * @param  int $attachment_id Attachment ID.
	 * @return boolean
	 */
	public function can_user_view( $attachment_id ) {

		if ( ! $attachment_id ) {
			return false;
		}

		$private_status = $this->is_attachment_private( $attachment_id );

		if ( ! empty( $private_status ) && ! is_user_logged_in() ) {
			return false;
		}

		return true;

	}

	/**
	 * Output link to file if user is logged in.
	 * Else output a message.
	 *
	 * @param  array $atts shortcode attributes.
	 * @return string shortcode output.
	 */
	public function get_private_url( $atts ) {

		if ( $this->is_attachment_private( $atts['id'] ) && ! is_user_logged_in() ) {
			$link = esc_html__( 'You must be logged in to access this file.', 'private-media' );
		} elseif ( isset( $atts['attachment_page'] ) ) {
			$link = wp_get_attachment_link( $atts['id'] );
		} else {
			$link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_get_attachment_url( $atts['id'] ) ),
				esc_html( basename( wp_get_attachment_url( $atts['id'] ) ) )
			);
		}

		return $link;

	}

	/**
	 * Get attachment id from attachment name
	 *
	 * @param  string $attachment Attachment name.
	 * @return int Attachment ID.
	 */
	public function get_attachment_id_from_name( $attachment ) {

		$attachment_post = new \WP_Query( array(
			'post_type'    => 'attachment',
			'showposts'    => 1,
			'post_status'  => 'inherit',
			'name'         => $attachment,
			'show_private' => true,
		) );

		if ( empty( $attachment_post->posts ) ) {
			return;
		}

		return reset( $attachment_post->posts )->ID;

	}
}
