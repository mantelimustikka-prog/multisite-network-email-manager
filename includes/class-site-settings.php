<?php
/**
 * Per-site SMTP/sender override settings.
 */
class MNEM_Site_Settings {
	const OPTION_KEY = 'mnem_site_smtp_settings';

	/**
	 * Logger instance.
	 *
	 * @var MNEM_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MNEM_Logger $logger Logger instance.
	 */
	public function __construct( MNEM_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Default per-site settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'override_enabled' => false,
			'from_email'       => '',
			'from_name'        => '',
			'reply_to_email'   => '',
			'reply_to_name'    => '',
		);
	}

	/**
	 * Get per-site settings for the current or given site.
	 *
	 * @param int|null $blog_id Blog ID, or null for current site.
	 * @return array<string,mixed>
	 */
	public function get( $blog_id = null ) {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Update per-site settings for the current site.
	 *
	 * @param array $input Raw input.
	 * @return array<string,mixed>
	 */
	public function update( array $input ) {
		$sanitized = $this->sanitize_settings( $input );
		update_option( self::OPTION_KEY, $sanitized );

		return $sanitized;
	}

	/**
	 * Sanitize per-site settings input.
	 *
	 * @param array $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( array $input ) {
		return array(
			'override_enabled' => ! empty( $input['override_enabled'] ),
			'from_email'       => sanitize_email( wp_unslash( $input['from_email'] ?? '' ) ),
			'from_name'        => sanitize_text_field( wp_unslash( $input['from_name'] ?? '' ) ),
			'reply_to_email'   => sanitize_email( wp_unslash( $input['reply_to_email'] ?? '' ) ),
			'reply_to_name'    => sanitize_text_field( wp_unslash( $input['reply_to_name'] ?? '' ) ),
		);
	}

	/**
	 * Register site admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_mnem_site_smtp_save', array( $this, 'handle_save' ) );
	}

	/**
	 * Register the per-site settings admin page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_options_page(
			__( 'Email Override', 'multisite-network-email-manager' ),
			__( 'Email Override', 'multisite-network-email-manager' ),
			'manage_options',
			'mnem-site-email-override',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the per-site settings page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'multisite-network-email-manager' ) );
		}

		$settings = $this->get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Email Override', 'multisite-network-email-manager' ); ?></h1>
			<p><?php esc_html_e( 'Override the sender and reply-to fields for emails sent from this site. The network SMTP connection is always used.', 'multisite-network-email-manager' ); ?></p>
			<?php $this->render_notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mnem_site_smtp_save" />
				<?php wp_nonce_field( 'mnem_site_smtp_save' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable override', 'multisite-network-email-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="override_enabled" value="1" <?php checked( ! empty( $settings['override_enabled'] ) ); ?> />
									<?php esc_html_e( 'Override network sender defaults for this site', 'multisite-network-email-manager' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="site_from_email"><?php esc_html_e( 'From email', 'multisite-network-email-manager' ); ?></label></th>
							<td><input class="regular-text" type="email" id="site_from_email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="site_from_name"><?php esc_html_e( 'From name', 'multisite-network-email-manager' ); ?></label></th>
							<td><input class="regular-text" type="text" id="site_from_name" name="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="site_reply_to_email"><?php esc_html_e( 'Reply-to email', 'multisite-network-email-manager' ); ?></label></th>
							<td><input class="regular-text" type="email" id="site_reply_to_email" name="reply_to_email" value="<?php echo esc_attr( $settings['reply_to_email'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="site_reply_to_name"><?php esc_html_e( 'Reply-to name', 'multisite-network-email-manager' ); ?></label></th>
							<td><input class="regular-text" type="text" id="site_reply_to_name" name="reply_to_name" value="<?php echo esc_attr( $settings['reply_to_name'] ); ?>" /></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save email override settings', 'multisite-network-email-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle per-site settings save.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'multisite-network-email-manager' ) );
		}

		check_admin_referer( 'mnem_site_smtp_save' );

		$this->update( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$this->logger->log( 'info', 'Per-site email override settings updated.' );

		$url = add_query_arg(
			array(
				'page'         => 'mnem-site-email-override',
				'mnem_notice'  => 'success',
				'mnem_message' => rawurlencode( __( 'Email override settings saved.', 'multisite-network-email-manager' ) ),
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render a page notice from query string.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['mnem_notice'] ) || empty( $_GET['mnem_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$type    = sanitize_key( wp_unslash( $_GET['mnem_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['mnem_message'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class   = 'error' === $type ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}
}
