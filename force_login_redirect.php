<?php
// Force login if the user is not on the homepage and not logged in
function force_login_redirect() {
        if ( !is_user_logged_in() 
          && !is_front_page() 
          && !is_home() //blog index page
            && !is_page('registration')
            && !is_page('registration-success') 
            && !is_page('reset-password')
          && !( defined('DOING_AJAX') && DOING_AJAX ) // AJAX requests should not be redirected
          && !is_admin() 
          && strpos( $_SERVER['REQUEST_URI'], '/wp-json/' ) === false ) {
        wp_redirect( wp_login_url() ); // Redirect to the login page
        exit;
    }
}
add_action( 'template_redirect', 'force_login_redirect' );

// Redirect users to the homepage after logout
function redirect_after_logout() {
    wp_redirect( home_url() ); // Redirect to the homepage
    exit;
}
add_action( 'wp_logout', 'redirect_after_logout' );
