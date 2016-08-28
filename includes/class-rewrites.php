<?php
namespace PrivateMedia;

class Rewrites {

	private $utilities;
	private $slug;

	public function __construct() {
		$this->utilities = new Utilities;
	}

	/**
	 * Instantiate any WP hooks that need to be fired.
	 */
	public function hooks() {

		add_action( 'init', array( $this, 'rewrite_rules' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'private_file_url' ), 10, 2 );

		// Display private posts filter & query filter.
		add_filter( 'pre_get_posts', array( $this, 'hide_private_from_query' ) );


	}

	/**
	 * Set a reference to the main plugin instance.
	 *
	 * @param $plugin Plugin instance.
	 * @return Database instance
	 */
	public function set_plugin( $plugin ) {

		$this->plugin = $plugin;
		$this->slug   = $this->plugin->definitions->slug;
		return $this;

	}

	/**
	 * Get Private Directory URL
	 *
	 * If $path is true return path not url.
	 *
	 * @param  boolean $path return path not url.
	 * @return string path or url
	 */
	function get_private_dir( $path = false ) {

		$dirname = 'private-files-' . Utilities::get_hash();
		$upload_dir = wp_upload_dir();

		// Maybe create the directory.
		if ( ! is_dir( trailingslashit( $upload_dir['basedir'] ) . $dirname ) ) {
			wp_mkdir_p( trailingslashit( $upload_dir['basedir'] ) . $dirname );
		}

		$htaccess = trailingslashit( $upload_dir['basedir'] ) . $dirname . '/.htaccess';

		if ( ! file_exists( $htaccess ) && function_exists( 'insert_with_markers' ) && is_writable( dirname( $htaccess ) ) ) {

			$contents[]	= "# This .htaccess file ensures that other people cannot download your private files.\n\n";
			$contents[] = "deny from all";

			insert_with_markers( $htaccess, $this->slug, $contents );

		}

		if ( $path ) {
			return trailingslashit( $upload_dir['basedir'] ) . $dirname;
		}

		return trailingslashit( $upload_dir['baseurl'] ) . $dirname;

	}

	function rewrite_rules() {

		// hm_add_rewrite_rule( array(
		// 	'regex' => '^content/uploads/private-files/([^*]+)/([^*]+)?$',
		// 	'query' => 'file_id=$matches[1]&file_name=$matches[2]',
		// 	'request_method' => 'get',
		// 	'request_callback' => array( $this, 'rewrite_callback' )
		// ) );

	}

	function rewrite_callback( $wp ) {

		if ( ! empty( $wp->query_vars['file_id'] ) ) {
			$file_id = $wp->query_vars['file_id'];
		}

		if ( ! empty( $wp->query_vars['file_name'] ) ) {
			$file_name = $wp->query_vars['file_name'];
		}

		// Legagcy
		if ( empty( $file_id ) ) {
 			preg_match( "#(&|^)file_id=([^&$]+)#", $wp->matched_query, $file_id_matches );
 			if ( $file_id_matches ) {
 				$file_id = $file_id_matches[2];
			}
			preg_match( "#(&|^)file_name=([^&$]+)#", $wp->matched_query, $file_name_matches );
				$file_name = $file_name_matches[2];
		}

		if ( ! isset( $file_id ) || isset( $file_id ) && ! $file = get_post( $file_id ) ) {
			auth_redirect();
		}

		$wp_attached_file = get_post_meta( $file_id, '_wp_attached_file', true );

		if ( ( $this->is_attachment_private( $file_id ) && ! is_user_logged_in() ) || empty( $wp_attached_file ) ) {
			auth_redirect();
		}

		$uploads = wp_upload_dir();
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
	 * @param  object $query
	 * @return object $query
	 */
	function hide_private_from_query( $query ) {

		if ( ! is_admin() ) {

			$attachment = ( $query->get( 'attachment_id' ) ) ? $query->get( 'attachment_id' ) : $query->get( 'attachment' );

			if ( $attachment && ! is_numeric( $attachment ) ) {
				$attachment = $this->get_attachment_id_from_name( $attachment );
			}

			if ( $attachment && ! $this->can_user_view( $attachment ) ) {

				$query->set_404();
				return $query;

			}

		}

		if ( 'attachment' == $query->get( 'post_type' ) && ! $query->get( 'show_private' ) ) {

			if ( isset( $_GET['private_posts'] ) && 'private' == $_GET['private_posts']  ) {
				$query->set( 'meta_query', array(
					array(
						'key'     => $this->slug . '_is_private',
						'compare' => 'EXISTS'
					)
				));
			} else {
				$query->set( 'meta_query', array(
					array(
						'key'     => $this->slug . '_is_private',
						'compare' => 'NOT EXISTS'
					)
				));
			}
		}

		return $query;

	}

	/**
	 * Filter attachment url.
	 * If private return the 'public' private file url
	 * Rewrite rule used to serve file content and 'Real' file location is obscured.
	 *
	 * @param  string $url
	 * @param  int $attachment_id
	 * @return string file url.
	 */
	function private_file_url( $url, $attachment_id ) {

		if ( $this->utilities->is_attachment_private( $attachment_id ) ) {

			$uploads = wp_upload_dir();
			return trailingslashit( $uploads['baseurl'] ) . 'private-files/' . $attachment_id . '/' . basename( $url );

		}

		return $url;

	}

}
