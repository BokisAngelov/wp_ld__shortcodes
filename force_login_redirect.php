<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Force login if the user is not on the homepage and not logged in.
function force_login_redirect() {
	if (
		is_user_logged_in()
		|| is_front_page()
		|| is_home() // Blog index page.
		|| is_page( array( 'registration', 'registration-success', 'reset-password' ) )
		|| wp_doing_ajax()
		|| is_admin()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
	) {
		return;
	}

	// Note: REST requests never reach template_redirect. The previous check
	// strpos( REQUEST_URI, '/wp-json/' ) let anyone bypass the lock by adding
	// "?x=/wp-json/" to any URL, so it was removed.
	wp_safe_redirect( wp_login_url() );
	exit;
}
add_action( 'template_redirect', 'force_login_redirect' );

// Redirect users to the homepage after logout.
function redirect_after_logout() {
	wp_safe_redirect( home_url() );
	exit;
}
add_action( 'wp_logout', 'redirect_after_logout' );
