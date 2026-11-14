<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [display_registered_users] shortcode.
 *
 * Lists every user's name, email, role and country, which is personal data. It is
 * therefore restricted to users who may list users (administrators by default).
 * To allow another capability: add_filter( 'display_registered_users_capability', fn() => 'edit_posts' );
 */
function display_registered_users() {

	$capability = apply_filters( 'display_registered_users_capability', 'list_users' );
	if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
		return '';
	}

	$user_data = array();

	foreach ( get_users() as $user ) {
		$country = function_exists( 'get_field' ) ? get_field( 'user_country', 'user_' . $user->ID ) : ''; // ACF field for country.
		$role    = function_exists( 'get_field' ) ? get_field( 'role', 'user_' . $user->ID ) : '';

		$user_data[] = array(
			'first_name' => get_user_meta( $user->ID, 'first_name', true ),
			'last_name'  => get_user_meta( $user->ID, 'last_name', true ),
			'email'      => $user->user_email,
			'country'    => is_array( $country ) && isset( $country['label'] ) ? $country['label'] : (string) $country,
			'role'       => is_scalar( $role ) ? (string) $role : '',
		);
	}

	// Sort the users by the country field.
	usort(
		$user_data,
		static function ( $a, $b ) {
			return strcmp( $a['country'], $b['country'] );
		}
	);

	$output  = '<table class="uk-text-center">';
	$output .= '<tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Role</th><th>Country</th></tr>';

	foreach ( $user_data as $row ) {
		$output .= '<tr>';
		$output .= '<td>' . esc_html( $row['first_name'] ) . '</td>';
		$output .= '<td>' . esc_html( $row['last_name'] ) . '</td>';
		$output .= '<td>' . esc_html( $row['email'] ) . '</td>';
		$output .= '<td>' . esc_html( $row['role'] ) . '</td>';
		$output .= '<td>' . esc_html( $row['country'] ) . '</td>';
		$output .= '</tr>';
	}

	return $output . '</table>';
}
add_shortcode( 'display_registered_users', 'display_registered_users' );
