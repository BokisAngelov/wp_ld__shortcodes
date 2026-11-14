<?php
/**
 * FORCE IMPORT
 *
 * One-off, standalone admin script. Upload next to wp-load.php, run, then DELETE IT.
 * It ignores headers to avoid encoding errors and assumes this CSV column order:
 * 0:username, 1:email, 2:password, 3:firstname, 4:lastname, 5:group_id, 6:organisation
 *
 * Security notes:
 * - Admin only, and the import only runs after clicking a button (POST + nonce), so
 *   opening or embedding the URL cannot trigger it (CSRF).
 * - Every value from the CSV is HTML-escaped before it is printed.
 * - Existing administrators/editors are never modified.
 * - Keep the CSV outside the web root (set $csv_file to an absolute path) and delete
 *   it afterwards: it contains plaintext passwords.
 */

require_once __DIR__ . '/wp-load.php';

$csv_file        = 'file-name.csv'; // Prefer an absolute path outside the web root.
$target_group_id = 2123; // LD group id.

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access Denied. Admin only.', '', array( 'response' => 403 ) );
}
if ( ! file_exists( $csv_file ) ) {
	wp_die( 'Error: file ' . esc_html( $csv_file ) . ' not found in ' . esc_html( getcwd() ) );
}

echo '<style>body{font-family:sans-serif; padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>';

if ( ! isset( $_POST['ld_force_import'] ) || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'ld_force_import' ) ) {
	echo '<h2>Ready to import</h2><p>File: <code>' . esc_html( $csv_file ) . '</code> into group ' . (int) $target_group_id . '.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'ld_force_import' );
	echo '<button type="submit" name="ld_force_import" value="1">Run import</button></form>';
	exit;
}

$handle = fopen( $csv_file, 'r' );
if ( ! $handle ) {
	wp_die( 'Could not open the CSV file.' );
}
$row_count = 0;

echo '<h2>Starting Import V2...</h2>';

while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
	++$row_count;

	$username   = trim( $data[0] ?? '' );
	$email      = trim( $data[1] ?? '' );
	$password   = trim( $data[2] ?? '' );
	$first_name = trim( $data[3] ?? '' );
	$last_name  = trim( $data[4] ?? '' );
	// Col 5 is group (skipping, we use the setting above).
	$org = trim( $data[6] ?? '' );

	$u = esc_html( $username ); // Already escaped: safe to print.
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $u is escaped.

	if ( '' === $username || '' === $email || ! is_email( $email ) ) {
		echo "<div class='error'>Row " . (int) $row_count . ': Skipped empty or invalid data row.</div>';
		continue;
	}

	$user_id = username_exists( $username );
	if ( ! $user_id ) {
		$user_id = email_exists( $email );
	}

	if ( ! $user_id ) {
		if ( '' === $password ) {
			$password = wp_generate_password( 20 );
		}
		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			echo "<div class='error'>Row " . (int) $row_count . ": Failed to create {$u}. Error: " . esc_html( $user_id->get_error_message() ) . '</div>';
			continue;
		}
		echo "<div class='success'>Row " . (int) $row_count . ": Created user <strong>{$u}</strong> (" . (int) $user_id . ').</div>';
	} else {
		$existing = get_userdata( $user_id );
		if ( $existing && array_diff( (array) $existing->roles, array( 'subscriber', 'customer' ) ) ) {
			echo "<div class='error'>Row " . (int) $row_count . ": {$u} is an existing privileged account. Skipped.</div>";
			continue;
		}
		echo "<div class='info'>Row " . (int) $row_count . ": User <strong>{$u}</strong> already exists. Updating details...</div>";
	}

	update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
	echo " - Toolbar: <span style='color:gray;'>Hidden</span><br>";

	if ( ! empty( $org ) && function_exists( 'update_field' ) ) {
		update_field( 'organisation', $org, 'user_' . $user_id );
		echo ' - Organisation: ' . esc_html( $org ) . '<br>';
	}

	if ( function_exists( 'ld_update_group_access' ) ) {
		ld_update_group_access( $user_id, $target_group_id );
		echo " - Group: <span style='color:green; font-weight:bold;'>Assigned to " . (int) $target_group_id . '</span><br>';
	}
	echo '<hr>';
}

fclose( $handle );
echo '<h3>Import Complete. Delete this file and the CSV immediately.</h3>';
