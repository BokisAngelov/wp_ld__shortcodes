<?php 
/**
 * LearnDash registration: Cloudflare Turnstile validation.
 *
 */

add_action('plugins_loaded', function () {
    remove_action('registration_errors', 'cfturnstile_wp_register_check', 10, 3);
}, 20);

//  Validate the Turnstile token on form submission.
add_filter('registration_errors', function ($errors, $sanitized_user_login, $user_email) {

    static $checked = false;
    if ( $checked ) {
        return $errors;
    }
    $checked = true;

    $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';

    if ( empty($token) ) {
        $errors->add('turnstile_missing', __('ERROR: Please verify that you are human.'));
        return $errors;
    }

    $secret = defined('CF_TURNSTILE_SECRET_KEY') ? CF_TURNSTILE_SECRET_KEY : get_option('cf_turnstile_secret_key');


    $resp = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'timeout' => 10,
        'body'    => [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ],
    ]);

    if ( is_wp_error($resp) ) {
        $errors->add('turnstile_http_error', __('ERROR: Human verification failed. Please try again.'));
        return $errors;
    }

    $body = wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);


    if ( empty($data['success']) ) {
        $errors->add('turnstile_failed', __('ERROR: Please verify that you are human.'));
    }

    return $errors;
}, 10, 3);

?>
