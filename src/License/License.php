<?php
/**
 * Zhaket license activation and periodic validation.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\License;

use Yuniq\Ai\Contracts\HookableInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to the Zhaket Guard service (guard.zhaket.com).
 *
 * - The buyer enters the license code once on the "لایسنس" screen; it is
 *   installed for this domain with the product token from Zhaket.
 * - Once a day the stored code is re-validated in the background and the
 *   result cached in an option, so no page load ever waits on Zhaket.
 * - A network failure never revokes a license: the last known good status
 *   is kept for a grace period, because outbound connections from Iranian
 *   hosts are often flaky.
 * - Without an active license the public chat widget stays off; the admin
 *   screens stay usable so the owner can configure and then activate.
 *
 * When YUNIQ_AI_ZHAKET_PRODUCT_TOKEN is empty (development builds) the
 * whole check is skipped.
 */
final class License implements HookableInterface {

	const OPTION     = 'yuniq_ai_license';
	const CRON_HOOK  = 'yuniq_ai_license_check';
	const PAGE_SLUG  = 'yuniq-ai-license';
	const CAPABILITY = 'manage_options';
	const GRACE_DAYS = 7;

	/**
	 * Guard API bases, tried in order.
	 *
	 * @var string[]
	 */
	private $endpoints = array(
		'https://guard.zhaket.com/api/',
		'https://guard.zhaket.org/api/',
		'http://guard.zhaket.com/api/',
	);

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'revalidate' ) );

		if ( ! self::enforced() ) {
			return;
		}

		add_filter( 'yuniq_ai_is_active', array( $this, 'gate_widget' ), 99 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
			add_action( 'admin_init', array( $this, 'schedule' ) );
			add_action( 'admin_post_yuniq_ai_license', array( $this, 'handle_form' ) );
			add_action( 'admin_notices', array( $this, 'notice' ) );
		}
	}

	/**
	 * Licensing is only enforced once a product token has been compiled in.
	 *
	 * @return bool
	 */
	public static function enforced() {
		return defined( 'YUNIQ_AI_ZHAKET_PRODUCT_TOKEN' ) && '' !== (string) YUNIQ_AI_ZHAKET_PRODUCT_TOKEN;
	}

	/**
	 * Whether this site holds a working license.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! self::enforced() ) {
			return true;
		}

		$state = self::state();

		if ( 'active' === $state['status'] ) {
			return true;
		}

		// Unreachable server: keep the last good result for a while.
		return 'unreachable' === $state['status']
			&& $state['valid_at'] > 0
			&& ( time() - $state['valid_at'] ) < self::GRACE_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * Stored license state with defaults.
	 *
	 * @return array{token:string,status:string,message:string,checked_at:int,valid_at:int}
	 */
	public static function state() {
		$state = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'token'      => '',
				'status'     => 'none',
				'message'    => '',
				'checked_at' => 0,
				'valid_at'   => 0,
			)
		);
	}

	/**
	 * Filter callback: hide the widget without a license.
	 *
	 * @param bool $active Current decision.
	 * @return bool
	 */
	public function gate_widget( $active ) {
		return $active && self::is_active();
	}

	/**
	 * Daily re-validation event.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Re-check the stored code with Zhaket.
	 *
	 * @return void
	 */
	public function revalidate() {
		$state = self::state();

		if ( '' === $state['token'] || ! self::enforced() ) {
			return;
		}

		$result = $this->request( 'validation-license', array( 'token' => $state['token'] ) );
		$this->save( $state['token'], $result );
	}

	/**
	 * Handle the activate / deactivate form.
	 *
	 * @return void
	 */
	public function handle_form() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yuniq-ai' ) );
		}

		check_admin_referer( 'yuniq_ai_license' );

		$action = isset( $_POST['license_action'] ) ? sanitize_key( wp_unslash( $_POST['license_action'] ) ) : '';

		if ( 'remove' === $action ) {
			delete_option( self::OPTION );
		} else {
			$token = isset( $_POST['license_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license_token'] ) ) ) : '';

			if ( '' === $token ) {
				$this->save( '', array( 'ok' => false, 'unreachable' => false, 'message' => __( 'کد لایسنس را وارد کنید.', 'yuniq-ai' ) ) );
			} else {
				$result = $this->request(
					'install-license',
					array(
						'product_token' => (string) YUNIQ_AI_ZHAKET_PRODUCT_TOKEN,
						'token'         => $token,
					)
				);
				$this->save( $token, $result );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Persist a request result.
	 *
	 * @param string                                        $token  License code.
	 * @param array{ok:bool,unreachable:bool,message:string} $result Normalised result.
	 * @return void
	 */
	private function save( $token, array $result ) {
		$state = self::state();

		$state['token']      = $token;
		$state['checked_at'] = time();
		$state['message']    = $result['message'];

		if ( $result['ok'] ) {
			$state['status']   = 'active';
			$state['valid_at'] = time();
		} elseif ( $result['unreachable'] ) {
			$state['status'] = 'unreachable';
		} else {
			$state['status'] = 'invalid';
		}

		update_option( self::OPTION, $state, false );
	}

	/**
	 * Call a Guard API method, falling back across hosts.
	 *
	 * @param string $method API method.
	 * @param array  $params Query parameters.
	 * @return array{ok:bool,unreachable:bool,message:string}
	 */
	private function request( $method, array $params ) {
		$params['domain'] = self::domain();
		$last_error       = '';

		foreach ( $this->endpoints as $base ) {
			$response = wp_remote_get(
				add_query_arg( array_map( 'rawurlencode', $params ), $base . $method ),
				array( 'timeout' => 15 )
			);

			if ( is_wp_error( $response ) || 0 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$last_error = is_wp_error( $response ) ? $response->get_error_message() : 'no response';
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! is_object( $body ) || ! isset( $body->status ) ) {
				$last_error = 'bad response';
				continue;
			}

			return array(
				'ok'          => 'successful' === $body->status,
				'unreachable' => false,
				'message'     => self::flatten( isset( $body->message ) ? $body->message : '' ),
			);
		}

		return array(
			'ok'          => false,
			'unreachable' => true,
			/* translators: %s: technical error */
			'message'     => sprintf( __( 'ارتباط با سرور ژاکت برقرار نشد (%s). اگر تکرار شد، از هاستینگ بخواهید دسترسی خروجی به guard.zhaket.com را باز کند.', 'yuniq-ai' ), $last_error ),
		);
	}

	/**
	 * Zhaket returns either a string or nested arrays of messages.
	 *
	 * @param mixed $message Raw message.
	 * @return string
	 */
	private static function flatten( $message ) {
		if ( is_scalar( $message ) ) {
			return (string) $message;
		}

		$out = array();
		array_walk_recursive(
			$message,
			static function ( $item ) use ( &$out ) {
				$out[] = (string) $item;
			}
		);

		return implode( ' ', $out );
	}

	/**
	 * Domain the license is bound to: host without port or leading www.
	 *
	 * @return string
	 */
	public static function domain() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return preg_replace( '/^www\./i', '', strtolower( $host ) );
	}

	/**
	 * Add the "لایسنس" submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'yuniq-ai',
			__( 'لایسنس', 'yuniq-ai' ),
			__( 'لایسنس', 'yuniq-ai' ) . ( self::is_active() ? '' : ' <span class="update-plugins count-1"><span class="update-count">!</span></span>' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Reminder on the dashboard and plugin screens until activated.
	 *
	 * @return void
	 */
	public function notice() {
		if ( self::is_active() || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && false !== strpos( (string) $screen->id, self::PAGE_SLUG ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'دستیار Yuniq.ai هنوز فعال نشده و در سایت نمایش داده نمی‌شود.', 'yuniq-ai' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'وارد کردن کد لایسنس', 'yuniq-ai' )
		);
	}

	/**
	 * License screen.
	 *
	 * @return void
	 */
	public function render_page() {
		$state  = self::state();
		$active = self::is_active();
		?>
		<div class="wrap" dir="rtl" style="max-width:640px">
			<h1><?php esc_html_e( 'لایسنس Yuniq.ai', 'yuniq-ai' ); ?></h1>

			<?php if ( $state['message'] ) : ?>
				<div class="notice <?php echo $active ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $state['message'] ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'yuniq_ai_license' ); ?>
				<input type="hidden" name="action" value="yuniq_ai_license" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'وضعیت', 'yuniq-ai' ); ?></th>
						<td><strong style="color:<?php echo $active ? '#12805c' : '#b32d2e'; ?>"><?php echo $active ? esc_html__( 'فعال', 'yuniq-ai' ) : esc_html__( 'غیرفعال', 'yuniq-ai' ); ?></strong>
						&nbsp;·&nbsp;<?php esc_html_e( 'دامنه:', 'yuniq-ai' ); ?> <code><?php echo esc_html( self::domain() ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><label for="yuniq-ai-license-token"><?php esc_html_e( 'کد لایسنس', 'yuniq-ai' ); ?></label></th>
						<td>
							<input type="text" id="yuniq-ai-license-token" name="license_token" class="regular-text" dir="ltr" value="<?php echo esc_attr( $state['token'] ); ?>" <?php disabled( $active ); ?> />
							<p class="description"><?php esc_html_e( 'کد لایسنس را از حساب کاربری ژاکت ← دانلودها بردارید. هر لایسنس برای یک دامنه است.', 'yuniq-ai' ); ?></p>
						</td>
					</tr>
				</table>
				<?php if ( $active ) : ?>
					<button type="submit" name="license_action" value="remove" class="button"><?php esc_html_e( 'حذف لایسنس از این سایت', 'yuniq-ai' ); ?></button>
				<?php else : ?>
					<button type="submit" name="license_action" value="install" class="button button-primary"><?php esc_html_e( 'فعال‌سازی', 'yuniq-ai' ); ?></button>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
