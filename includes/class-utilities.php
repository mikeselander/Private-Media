<?php
namespace PrivateMedia;

class Utilities {

	private $plugin;
	private $slug;
	private static $static_slug;

	public function __construct() {
		$this->slug = plugin()->get_definitions()->slug;
		self::$static_slug = $this->slug;
	}

	public static function get_hash() {
		return apply_filters( self::$static_slug . '_hash', hash( 'md5', AUTH_KEY ) );
	}

	/**
	 * Check if attachment is private
	 *
	 * @param  int $attachment_id
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
	 * @param  int $attachment_id
	 * @param  int $user_id (if not passed, assumed current user)
	 * @return boolean
	 */
	public function can_user_view( $attachment_id, $user_id = null ) {

		$user_id = ( $user_id ) ? $user_id : get_current_user_id();

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
	 * @param  array $atts shortcode attributes
	 * @return string shortcode output.
	 */
	public function get_private_url( $atts ) {

		if ( $this->is_attachment_private( $atts['id'] ) && ! is_user_logged_in() ){
			$link = 'You must be logged in to access this file.';
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
	 * @todo  surely this isn't the best way to do this?
	 * @param  [type] $attachment [description]
	 * @return [type]             [description]
	 */
	public function get_attachment_id_from_name( $attachment ) {

		$attachment_post = new WP_Query( array(
			'post_type'    => 'attachment',
			'showposts'    => 1,
			'post_status'  => 'inherit',
			'name'         => $attachment,
			'show_private' => true
		) );

		if ( empty( $attachment_post->posts ) ) {
			return;
		}

		return reset( $attachment_post->posts )->ID;

	}
}
