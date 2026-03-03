<?php
/**
 * LESSON CERTIFICATE SYSTEM
 *
 * Generates a lesson-specific PDF certificate using LearnDash's own TCPDF engine.
 * 
 */

/** Shared context passed from the endpoint to the template-tag handlers. */
global $cardet_cert_lesson_context;
$cardet_cert_lesson_context = [];

/**
 * Template tag [cardet_lesson_title] — outputs the lesson title inside a certificate.
 */
add_shortcode( 'cardet_lesson_title', 'cardet_cert_tag_lesson_title' );
function cardet_cert_tag_lesson_title() {
    global $cardet_cert_lesson_context;
    $lesson_id = $cardet_cert_lesson_context['lesson_id'] ?? 0;
    return $lesson_id ? esc_html( get_the_title( $lesson_id ) ) : '';
}

/**
 * Template tag [cardet_lesson_completed_date] — outputs the lesson completion date.
 * Reads from the learndash_user_activity table; falls back to today's date.
 */
add_shortcode( 'cardet_lesson_completed_date', 'cardet_cert_tag_completed_date' );
function cardet_cert_tag_completed_date() {
    global $cardet_cert_lesson_context, $wpdb;
    $lesson_id = $cardet_cert_lesson_context['lesson_id'] ?? 0;
    $user_id   = $cardet_cert_lesson_context['user_id']   ?? 0;

    if ( ! $lesson_id || ! $user_id ) {
        return '';
    }

    $timestamp = $wpdb->get_var( $wpdb->prepare(
        "SELECT activity_completed
           FROM {$wpdb->prefix}learndash_user_activity
          WHERE user_id        = %d
            AND post_id        = %d
            AND activity_type  = 'lesson'
            AND activity_status = 1
          LIMIT 1",
        $user_id,
        $lesson_id
    ) );

    return date_i18n( get_option( 'date_format' ), $timestamp ?: current_time( 'timestamp' ) );
}

/**
 * Custom certificate endpoint.
 * This function is used to generate the certificate PDF for a lesson.
 *
 * Priority 1 ensures this fires before LearnDash's own template_redirect handler.
 */
add_action( 'template_redirect', 'cardet_lesson_cert_endpoint', 1 );
function cardet_lesson_cert_endpoint() {
    if ( empty( $_GET['cardet_cert'] ) ||
         empty( $_GET['cert_id'] )     ||
         empty( $_GET['lesson_id'] )   ||
         empty( $_GET['cert_nonce'] ) ) {
        return;
    }

    $cert_id   = absint( $_GET['cert_id'] );
    $lesson_id = absint( $_GET['lesson_id'] );

    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_GET['cert_nonce'] ) ),
        'cardet_cert_' . $cert_id . '_' . $lesson_id
    ) ) {
        wp_die( esc_html__( 'Security check failed.' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_die( esc_html__( 'You must be logged in to view this certificate.' ) );
    }

    $user_id   = get_current_user_id();
    $course_id = learndash_get_course_id( $lesson_id );

    if ( ! learndash_is_lesson_complete( $user_id, $lesson_id, $course_id ) ) {
        wp_die( esc_html__( 'You need to complete the lesson first.' ) );
    }

    // Populate context so our template tags know which lesson/user to reference.
    global $cardet_cert_lesson_context;
    $cardet_cert_lesson_context = [
        'lesson_id' => $lesson_id,
        'user_id'   => $user_id,
        'course_id' => $course_id,
    ];


    if ( ! function_exists( 'learndash_certificate_post_shortcode' ) ) {
        $ld_pdf = LEARNDASH_LMS_PLUGIN_DIR . 'includes/ld-convert-post-pdf.php';
        if ( file_exists( $ld_pdf ) ) {
            include_once $ld_pdf;
        }
    }

    // Generate and stream the PDF using LearnDash's TCPDF engine.
    learndash_certificate_post_shortcode( [
        'user_id' => $user_id,
        'cert_id' => $cert_id,
        'post_id' => $cert_id,
    ] );

    exit;
}

/**
 * [cardet_lesson_certificate] shortcode.
 *
 * Renders a download button when the current lesson is complete, or a locked
 * disabled button when it is not. Must be placed on a LearnDash lesson page.
 *
 * @param array $atts {
 *     @type int    $cert_id  Required. Post ID of the sfwd-certificates post.
 *     @type string $label    Optional. Button text. Default: 'Download your certificate'.
 * }
 */
add_shortcode( 'cardet_lesson_certificate', 'cardet_lesson_certificate' );
function cardet_lesson_certificate( $atts ) {
    $atts = shortcode_atts(
        [
            'cert_id' => '',
            'label'   => 'Download your certificate',
        ],
        $atts,
        'cardet_lesson_certificate'
    );

    if ( ! is_user_logged_in() || empty( $atts['cert_id'] ) ) {
        return '';
    }

    $user_id   = get_current_user_id();
    $lesson_id = get_the_ID();
    $course_id = learndash_get_course_id( $lesson_id );
    $cert_id   = absint( $atts['cert_id'] );
    $label     = esc_html( $atts['label'] );

    ob_start();

    if ( learndash_is_lesson_complete( $user_id, $lesson_id, $course_id ) ) {
        $cert_url = add_query_arg(
            [
                'cardet_cert' => 1,
                'cert_id'     => $cert_id,
                'lesson_id'   => $lesson_id,
                'cert_nonce'  => wp_create_nonce( 'cardet_cert_' . $cert_id . '_' . $lesson_id ),
            ],
            get_permalink( $cert_id )
        );
        echo '<a class="uk-button uk-button-primary" href="' . esc_url( $cert_url ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
    } else {
        echo '<a class="uk-button uk-button-secondary" disabled uk-tooltip="You need to complete all the Units to download your certificate"><span uk-icon="lock" class="uk-text-default uk-icon"></span> ' . $label . '</a>';
    }

    return ob_get_clean();
}

?>
