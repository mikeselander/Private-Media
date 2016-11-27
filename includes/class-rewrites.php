<?php
/**
 * Enable rewrites that will direct media paths to the correct locations.
 *
 * @package WordPress
 * @subpackage Private Media
 */

namespace PrivateMedia;

class Rewrites {

	/**
	 * Holder for Utilities class.
	 *
	 * @var Utilities
	 */
	private $utilities;

	/**
	 * Plugin slug used for settings.
	 *
	 * @var string
	 */
	private $slug;

	public function __construct() {
		$this->utilities = new Utilities;
	}

	/**
	 * Instantiate any WP hooks that need to be fired.
	 */
	public function hooks() {

		add_action( 'init', array( $this, 'rewrite_rules' ), 0, 20 );
		add_filter( 'wp_get_attachment_url', array( $this, 'private_file_url' ), 10, 2 );

		// Display private posts filter & query filter.
		add_filter( 'pre_get_posts', array( $this, 'hide_private_from_query' ) );

		// Re-direct from attachment singles if is private
		add_action( 'template_redirect', array( $this, 'redirect_attachment_single' ) );

	}

	/**
	 * Set a reference to the main plugin instance.
	 *
	 * @param object $plugin Plugin instance.
	 * @return Database instance
	 */
	public function set_plugin( $plugin ) {

		$this->plugin = $plugin;
		$this->slug   = $this->plugin->definitions->slug;
		return $this;

	}

	/**
	 * Get Private Directory URL.
	 *
	 * If $path is true return path not url.
	 *
	 * @param  boolean $path return path not url.
	 * @return string path or url
	 */
	public function get_private_dir( $path = false ) {

		$dirname    = 'private-files-' . Utilities::get_hash();
		$upload_dir = wp_upload_dir();

		// Maybe create the directory.
		if ( ! is_dir( trailingslashit( $upload_dir['basedir'] ) . $dirname ) ) {
			wp_mkdir_p( trailingslashit( $upload_dir['basedir'] ) . $dirname );
		}

		$htaccess = trailingslashit( $upload_dir['basedir'] ) . $dirname . '/.htaccess';

		if ( ! file_exists( $htaccess ) && function_exists( 'insert_with_markers' ) && is_writable( dirname( $htaccess ) ) ) {

			$contents[]	= esc_attr__( '# This .htaccess file ensures that other people cannot download your private files.\n\n', 'private-media' );
			$contents[] = 'deny from all';

			insert_with_markers( $htaccess, $this->slug, $contents );

		}

		if ( $path ) {
			return trailingslashit( $upload_dir['basedir'] ) . $dirname;
		}

		return trailingslashit( $upload_dir['baseurl'] ) . $dirname;

	}

	/**
	 * Add rewrite rules to the HM Rewrites API.
	 */
	public function rewrite_rules() {

		hm_add_rewrite_rule( array(
			'regex' => '^wp-content\/uploads\/private-files\/([^*]+)\/([^*]+)?$',
			'query' => 'file_id=$matches[1]&file_name=$matches[2]',
			'request_method' => 'get',
			'request_callback' => [ $this, 'rewrite_callback' ],
		) );

	}

	/**
	 * Callback for HM Rewrites - tells API where to redirect request.
	 *
	 * @param  object $wp Query object.
	 */
	public function rewrite_callback( $wp ) {

		if ( ! empty( $wp->query_vars['file_id'] ) ) {
			$file_id = $wp->query_vars['file_id'];
		}

		if ( ! empty( $wp->query_vars['file_name'] ) ) {
			$file_name = $wp->query_vars['file_name'];
		}

		// Legacy.
		if ( empty( $file_id ) ) {
 			preg_match( '#(&|^)file_id=([^&$]+)#', $wp->matched_query, $file_id_matches );

			if ( $file_id_matches ) {
				$file_id = $file_id_matches[2];
			}

			preg_match( '#(&|^)file_name=([^&$]+)#', $wp->matched_query, $file_name_matches );

			if ( $file_id_matches ) {
				$file_name = $file_name_matches[2];
			}
		}

		if ( ! isset( $file_id ) || isset( $file_id ) && ! $file = get_post( $file_id ) ) {
			auth_redirect();
		}

		$wp_attached_file = get_post_meta( $file_id, '_wp_attached_file', true );

		if ( ( $this->utilities->is_attachment_private( $file_id ) && ! is_user_logged_in() ) || empty( $wp_attached_file ) ) {
			auth_redirect();
		}

		$uploads   = wp_upload_dir();
		$file_path = trailingslashit( $uploads['basedir'] ) . $wp_attached_file;
		$mime_type = get_post_mime_type( $file_id );

		$file = fopen( $file_path, 'rb' );
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . filesize( $file_path ) );
		fpassthru( $file );
		exit;

	}

	/**
	 * Filter query to hide private posts.
	 *
	 * Set 404 for attachments in front end if user does not have permission to view file.
	 * Hide from any attachment query by default.
	 * If the 'show_private' query var is set, show only private.
	 *
	 * @param  object $query Query object.
	 * @return object Query object.
	 */
	public function hide_private_from_query( $query ) {

		if ( ! is_admin() ) {

			$attachment = ( $query->get( 'attachment_id' ) ) ? $query->get( 'attachment_id' ) : $query->get( 'attachment' );

			if ( $attachment && ! is_numeric( $attachment ) ) {
				$attachment = $this->utilities->get_attachment_id_from_name( $attachment );
			}

			if ( $attachment && ! $this->utilities->can_user_view( $attachment ) ) {

				$query->set_404();
				return $query;

			}
		}

		return $query;

	}

	/**
	 * Filter attachment url.
	 *
	 * If private return the 'public' private file url
	 * Rewrite rule used to serve file content and 'Real' file location is obscured.
	 *
	 * @param  string $url URL to potentially rewrite.
	 * @param  int    $attachment_id Attachment ID.
	 * @return string file url.
	 */
	public function private_file_url( $url, $attachment_id ) {

		if ( $this->utilities->is_attachment_private( $attachment_id ) ) {

			$uploads = wp_upload_dir();
			return trailingslashit( $uploads['baseurl'] ) . 'private-files/' . $attachment_id . '/' . basename( $url );

		}

		return $url;

	}

	/**
	 * Redirect logged-out users from attachment pages if attachment is private.
	 *
	 * Hooked into template_redirect action.
	 */
	public function redirect_attachment_single() {
		if ( ! is_user_logged_in() && is_singular( 'attachment' ) && $this->utilities->is_attachment_private( get_queried_object_id() ) ) {
			auth_redirect();
		}
	}
}
