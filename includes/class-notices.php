<?php
namespace PrivateMedia;

class Notices {

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

	private $ID;

	function __construct( $ID = 'mph' ) {

		$this->ID = 'mphan_' . $ID;

		if ( ! get_option( $this->ID ) )
			add_option( $this->ID, array(), null, 'no' );
		else
			$this->admin_notices = get_option( $this->ID, array() );

		if ( isset( $_GET[ $this->ID . '_notice_dismiss' ] ) || isset( $_GET['_wpnonce'] ) )
			add_action( 'admin_init', array( $this, 'delete_notice_action' ) );

		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

		add_action( 'shutdown', array( $this, '_update_notices' ) );

	}

	/**
	 * Creates an admin notice - saved in options to be shown in the admin, until dismissed.
	 *
	 * @param string $new_notice Message content
	 * @param string $type Message type - added as a class to the message when displayed. Reccommended to use: updated, error.
	 * @param bool $display_once Display message once, or require manual dismissal.
	 */
	public function add_notice( $message, $display_once = false, $type = 'updated' ) {

		$notice = array(
			'message' => $message,
			'type' => $type,
			'display_once' => $display_once
		);

		if ( ! in_array( $notice , $this->admin_notices ) )
			$this->admin_notices[uniqid()] = $notice;

	}

	public function _update_notices() {

		$this->admin_notices = array_filter( $this->admin_notices );

		if ( empty( $this->admin_notices ) ) {
			delete_option( $this->ID );
		} else {
			update_option( $this->ID, $this->admin_notices );
		}

	}

	/**
	 * Output all notices in the admin.
	 *
	 * @return null
	 */
	public function display_admin_notices() {

		// Display admin notices
		foreach ( array_keys( $this->admin_notices ) as $notice_id )
			$this->display_admin_notice( $notice_id );

	}

	/**
	 * Output an individual notice.
	 *
	 * @param  string $notice_id The notice id (or key)
	 * @return null
	 */
	private function display_admin_notice ( $notice_id ) {

		if ( ! $notice = $this->admin_notices[$notice_id] )
			return;

		?>

		<div class="<?php echo esc_attr( $notice['type'] ); ?> ' fade">

			<p>

				<?php echo $notice['message']; ?>

				<?php if ( empty( $notice['display_once'] ) ) : ?>
					<a class="button" style="margin-left: 10px; color: inherit; text-decoration: none;" href="<?php echo wp_nonce_url( add_query_arg( $this->ID . '_notice_dismiss', $notice_id ), $this->ID . '_notice_dismiss' ); ?>">Dismiss</a>
				<?php endif; ?>

			</p>

		</div>

		<?php

		if ( $notice['display_once'] )
			$this->unset_admin_notice( $notice_id );

	}

	/**
	 * Remove an admin notice by key from $this->admin_notices
	 *
	 * @param  string $notice_id Notice ID (or key)
	 * @return null
	 */
	private function unset_admin_notice( $notice_id ) {

		if ( array_key_exists( $notice_id, $this->admin_notices ) )
			unset( $this->admin_notices[$notice_id] );

	}

	/**
	 * Deletes an admin notice.
	 *
	 * Requirements:
	 * $this->ID . '_notice_dismiss' nonce verification
	 * value of $_GET[$this->ID . '_notice_dismiss'] si the ID of the notice to be deleted.
	 *
	 * @param null
	 * @return null
	 */
	public function delete_notice_action() {

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], $this->ID . '_notice_dismiss' ) )
			return;

		$this->unset_admin_notice( $_GET[$this->ID . '_notice_dismiss'] );

	}

}
