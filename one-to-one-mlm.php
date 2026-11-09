<?php
/**
 * Plugin Name: 1to1 MLM
 * Author Name: Paul Kato (pkats2013@gmail.com)
 * Description: Tracks member referrals and records configurable three-level WooCommerce commissions.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 1to1-mlm-plugin
 * Domain Path: /languages
 *
 * @package OneToOneMLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OneToOne_MLM {
	const OPTION = 'one_to_one_mlm_settings';
	const COOKIE = 'one_to_one_mlm_ref';
	const VERSION = '0.1.0';

	/** Register plugin hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'capture_referral' ) );
		add_action( 'user_register', array( __CLASS__, 'assign_sponsor' ) );
		add_shortcode( 'one_to_one_mlm_referral_link', array( __CLASS__, 'referral_link_shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_text' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'record_order_commissions' ) );
		}
	}

	/** Create the commission ledger table. */
	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'one_to_one_mlm_commissions';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			payee_user_id bigint(20) unsigned NOT NULL,
			origin_user_id bigint(20) unsigned NOT NULL,
			level tinyint(1) unsigned NOT NULL,
			amount decimal(18,4) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_payee_level (order_id,payee_user_id,level),
			KEY payee_user_id (payee_user_id)
		) {$charset_collate};";

		dbDelta( $sql );
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, array( 'level_1' => 0, 'level_2' => 0, 'level_3' => 0 ) );
		}
	}

	/** Store a valid referral ID for the registration flow. */
	public static function capture_referral() {
		if ( ! isset( $_GET['ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public referral URL parameter.
			return;
		}

		$referrer_id = absint( wp_unslash( $_GET['ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $referrer_id || ! get_user_by( 'id', $referrer_id ) ) {
			return;
		}

		$secure = is_ssl();
		setcookie(
			self::COOKIE,
			(string) $referrer_id,
			array(
				'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/** Attach a new account to its referring member once. */
	public static function assign_sponsor( $user_id ) {
		if ( get_user_meta( $user_id, '_one_to_one_mlm_sponsor', true ) || empty( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}

		$sponsor_id = absint( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		if ( $sponsor_id && $sponsor_id !== (int) $user_id && get_user_by( 'id', $sponsor_id ) ) {
			update_user_meta( $user_id, '_one_to_one_mlm_sponsor', $sponsor_id );
		}
	}

	/** Display the current member's referral URL. */
	public static function referral_link_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$url = add_query_arg( 'ref', get_current_user_id(), home_url( '/' ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
	}

	/** Add the plan settings screen. */
	public static function add_settings_page() {
		add_options_page(
			esc_html__( '1to1 MLM Plan', '1to1-mlm-plugin' ),
			esc_html__( '1to1 MLM Plan', '1to1-mlm-plugin' ),
			'manage_options',
			'1to1-mlm-plugin',
			array( __CLASS__, 'render_settings_page' )
		);
		add_options_page(
			esc_html__( '1to1 MLM Commissions', '1to1-mlm-plugin' ),
			esc_html__( 'Commission Records', '1to1-mlm-plugin' ),
			'manage_options',
			'1to1-mlm-plugin-commissions',
			array( __CLASS__, 'render_commissions_page' )
		);
	}

	/** Register the three configurable levels. */
	public static function register_settings() {
		register_setting(
			'one_to_one_mlm',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array( 'level_1' => 0, 'level_2' => 0, 'level_3' => 0 ),
			)
		);
	}

	/** Sanitize commission percentages. */
	public static function sanitize_settings( $input ) {
		$settings = array();
		$total    = 0;
		foreach ( array( 'level_1', 'level_2', 'level_3' ) as $level ) {
			$value                  = isset( $input[ $level ] ) ? (float) $input[ $level ] : 0;
			$settings[ $level ]     = min( 100, max( 0, $value ) );
			$total                 += $settings[ $level ];
		}
		if ( $total > 100 ) {
			add_settings_error( self::OPTION, 'total_rate', __( 'The three commission percentages cannot total more than 100%.', '1to1-mlm-plugin' ) );
			return get_option( self::OPTION, array( 'level_1' => 0, 'level_2' => 0, 'level_3' => 0 ) );
		}
		return $settings;
	}

	/** Add suggested text to the WordPress privacy policy guide. */
	public static function add_privacy_policy_text() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content = '<p>' . esc_html__( 'This site uses 1to1 MLM to store referral relationships between member accounts and, when WooCommerce is active, commission records linked to completed orders. Referral IDs may be stored in a first-party cookie for up to 30 days so a later registration can be attributed to the referring member. Commission records include the order ID, member IDs, level, amount, and record date. The site operator controls retention and should describe it in this policy.', '1to1-mlm-plugin' ) . '</p>';
			wp_add_privacy_policy_content( '1to1 MLM', wp_kses_post( $content ) );
		}
	}

	/** Render the plan settings page. */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = get_option( self::OPTION, array( 'level_1' => 0, 'level_2' => 0, 'level_3' => 0 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '1to1 MLM Plan', '1to1-mlm-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Set a percentage for each sponsor level. Rates start at zero. When WooCommerce is active, eligible completed orders create commission records; this plugin does not issue payments.', '1to1-mlm-plugin' ); ?></p>
			<form action="options.php" method="post">
				<?php settings_fields( 'one_to_one_mlm' ); ?>
				<?php foreach ( array( 'level_1' => 1, 'level_2' => 2, 'level_3' => 3 ) as $key => $number ) : ?>
					<p>
						<label for="<?php echo esc_attr( self::OPTION . '_' . $key ); ?>">
							<?php echo esc_html( sprintf( __( 'Level %d commission (%)', '1to1-mlm-plugin' ), $number ) ); ?>
						</label>
						<input type="number" min="0" max="100" step="0.01" id="<?php echo esc_attr( self::OPTION . '_' . $key ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( isset( $settings[ $key ] ) ? $settings[ $key ] : 0 ); ?>" />
					</p>
				<?php endforeach; ?>
				<?php submit_button(); ?>
			</form>
			<p><?php esc_html_e( 'Add [one_to_one_mlm_referral_link] to a page to show a logged-in member their referral link.', '1to1-mlm-plugin' ); ?></p>
		</div>
		<?php
	}

	/** Show the latest recorded commissions to site administrators. */
	public static function render_commissions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'one_to_one_mlm_commissions';
		$rows  = $wpdb->get_results( "SELECT order_id, payee_user_id, origin_user_id, level, amount, created_at FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses the trusted WordPress prefix.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Commission Records', '1to1-mlm-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Showing the latest 100 records. These are bookkeeping entries only; this plugin does not make payments or automatically reverse entries after refunds.', '1to1-mlm-plugin' ); ?></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Order', '1to1-mlm-plugin' ); ?></th>
					<th><?php esc_html_e( 'Payee member ID', '1to1-mlm-plugin' ); ?></th>
					<th><?php esc_html_e( 'Origin member ID', '1to1-mlm-plugin' ); ?></th>
					<th><?php esc_html_e( 'Level', '1to1-mlm-plugin' ); ?></th>
					<th><?php esc_html_e( 'Amount', '1to1-mlm-plugin' ); ?></th>
					<th><?php esc_html_e( 'Recorded (UTC)', '1to1-mlm-plugin' ); ?></th>
				</tr></thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No commission records yet.', '1to1-mlm-plugin' ); ?></td></tr>
					<?php else : foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['order_id'] ); ?></td>
							<td><?php echo esc_html( $row['payee_user_id'] ); ?></td>
							<td><?php echo esc_html( $row['origin_user_id'] ); ?></td>
							<td><?php echo esc_html( $row['level'] ); ?></td>
							<td><?php echo esc_html( $row['amount'] ); ?></td>
							<td><?php echo esc_html( $row['created_at'] ); ?></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** Record one commission per eligible ancestor and level for a completed order. */
	public static function record_order_commissions( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_customer_id() ) {
			return;
		}

		$settings        = get_option( self::OPTION, array() );
		$commission_base = (float) $order->get_total();
		$member_id       = (int) $order->get_customer_id();
		$seen            = array( $member_id );
		global $wpdb;
		$table           = $wpdb->prefix . 'one_to_one_mlm_commissions';

		for ( $level = 1; $level <= 3; $level++ ) {
			$member_id = (int) get_user_meta( $member_id, '_one_to_one_mlm_sponsor', true );
			if ( ! $member_id || in_array( $member_id, $seen, true ) ) {
				break;
			}
			$seen[] = $member_id;
			$rate   = isset( $settings[ 'level_' . $level ] ) ? (float) $settings[ 'level_' . $level ] : 0;
			if ( $rate <= 0 ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (order_id, payee_user_id, origin_user_id, level, amount, created_at) VALUES (%d, %d, %d, %d, %f, %s)",
					$order_id,
					$member_id,
					$order->get_customer_id(),
					$level,
					$commission_base * $rate / 100,
					current_time( 'mysql', true )
				)
			);
		}
	}
}

OneToOne_MLM::init();
register_activation_hook( __FILE__, array( 'OneToOne_MLM', 'activate' ) );
