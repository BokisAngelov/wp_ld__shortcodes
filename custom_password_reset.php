<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [password_reset_form] shortcode.
 *
 * Security notes:
 * - Same answer whether or not the account exists (no user enumeration).
 * - Nonce-protected form, and a throttle per visitor IP and per account.
 * - Respects WordPress' own `allow_password_reset` rules (no forced override).
 */
function custom_password_reset_form() {

	$message_html = '';

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified just below.
	if ( isset( $_POST['cprf_reset_password'] ) ) {
		$nonce = isset( $_POST['cprf_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cprf_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'cprf_reset_password' ) ) {
			$message_html = '<div class="uk-alert-danger"><p>' . esc_html__( 'Security check failed. Please reload the page and try again.' ) . '</p></div>';
		} else {
			$user_login = isset( $_POST['cprf_user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['cprf_user_login'] ) ) : '';
			$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

			// Throttle: max 5 requests per 15 minutes per IP, 1 per 2 minutes per account.
			$ip_key   = 'cprf_ip_' . md5( $ip );
			$user_key = 'cprf_user_' . md5( strtolower( $user_login ) );
			$ip_count = (int) get_transient( $ip_key );

			if ( $ip_count >= 5 || get_transient( $user_key ) ) {
				$message_html = '<div class="uk-alert-danger"><p>' . esc_html__( 'Too many requests. Please wait a few minutes and try again.' ) . '</p></div>';
			} else {
				set_transient( $ip_key, $ip_count + 1, 15 * MINUTE_IN_SECONDS );
				set_transient( $user_key, 1, 2 * MINUTE_IN_SECONDS );

				$user = '' !== $user_login ? get_user_by( 'login', $user_login ) : false;
				if ( ! $user && is_email( $user_login ) ) {
					$user = get_user_by( 'email', $user_login );
				}

				if ( $user && apply_filters( 'allow_password_reset', true, $user->ID ) ) {
					cprf_send_reset_email( $user );
				}

				// Identical message in every case.
				$message_html = '<div class="uk-alert-success"><p>' . esc_html__( 'If an account matches, a password reset link has been sent to its email address.' ) . '</p></div>';
			}
		}
	}

	ob_start();
	echo wp_kses_post( $message_html );
	?>
	<form method="post">
		<?php wp_nonce_field( 'cprf_reset_password', 'cprf_nonce' ); ?>
		<label for="cprf_user_login">Username or Email:</label>
		<input class="uk-input uk-form-width-medium" type="text" name="cprf_user_login" id="cprf_user_login" required><br><br>
		<input class="uk-button uk-button-primary" type="submit" name="cprf_reset_password" value="Send Reset Link">
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'password_reset_form', 'custom_password_reset_form' );

/**
 * Create a reset key and email the link. Failures are silent to the visitor.
 *
 * @param WP_User $user Account to reset.
 */
function cprf_send_reset_email( $user ) {
	$reset_key = get_password_reset_key( $user );
	if ( is_wp_error( $reset_key ) ) {
		return;
	}

	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$reset_url = add_query_arg(
		array(
			'action'  => 'rp',
			'key'     => rawurlencode( $reset_key ),
			'login'   => rawurlencode( $user->user_login ),
			'wp_lang' => 'en_US',
		),
		network_site_url( 'wp-login.php', 'login' )
	);

	$from_name  = 'The ' . $site_name . ' Team';
	$from_email = 'wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST );
	$headers    = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . str_replace( array( "\r", "\n" ), '', sanitize_text_field( $from_name ) ) . ' <' . sanitize_email( $from_email ) . '>',
	);

	$message = sprintf(
		'Hello %1$s,<br><br>You requested a password reset at "%2$s".<br><br>To reset your password click the link below or copy and paste it in your browser.<br><br><a href="%3$s">%4$s</a><br><br>If you did not request this, please ignore this email.<br><br>Best regards,<br>%5$s',
		esc_html( $user->display_name ),
		esc_html( $site_name ),
		esc_url( $reset_url ),
		esc_html( $reset_url ),
		esc_html( $from_name )
	);

	wp_mail( $user->user_email, sprintf( 'Password reset request at %s', $site_name ), $message, $headers );
}
