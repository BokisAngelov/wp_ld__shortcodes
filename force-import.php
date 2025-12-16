<?php
/**
 * FORCE IMPORT 
 * This script ignores headers to avoid encoding errors.
 * It assumes the CSV column order:
 * 0:username, 1:email, 2:password, 3:firstname, 4:lastname, 5:group_id, 6:organisation
 * write directly the value for each column
 */

require_once('wp-load.php');

$csv_file = 'file-name.csv';
$target_group_id = 2123; // LD group id

if ( !current_user_can('manage_options') ) { die('Access Denied. Admin only.'); }
if ( !file_exists($csv_file) ) { die("Error: File '$csv_file' not found in " . getcwd()); }

$handle = fopen($csv_file, 'r');
$row_count = 0;

echo '<style>body{font-family:sans-serif; padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>';
echo "<h2>Starting Import V2...</h2>";

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $row_count++;

    $username   = trim($data[0] ?? '');
    $email      = trim($data[1] ?? '');
    $password   = trim($data[2] ?? '');
    $first_name = trim($data[3] ?? '');
    $last_name  = trim($data[4] ?? '');
    // Col 5 is group (skipping, we use the setting above)
    $org        = trim($data[6] ?? '');

    if (empty($username) || empty($email)) {
        echo "<div class='error'>Row $row_count: Skipped empty data row.</div>";
        continue;
    }

    $user_id = username_exists($username);
    if (!$user_id) {
        $user_id = email_exists($email);
    }

    if (!$user_id) {
        $user_data = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => 'subscriber'
        ];
        
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            echo "<div class='error'>Row $row_count: Failed to create $username. Error: " . $user_id->get_error_message() . "</div>";
            continue;
        } else {
            echo "<div class='success'>Row $row_count: Created user <strong>$username</strong> ($user_id).</div>";
        }
    } else {
        echo "<div class='info'>Row $row_count: User <strong>$username</strong> already exists. Updating details...</div>";
    }

    if (!is_wp_error($user_id)) {
        
        update_user_meta($user_id, 'show_admin_bar_front', 'false');
        echo " - Toolbar: <span style='color:gray;'>Hidden</span><br>";

        if (!empty($org) && function_exists('update_field') && function_exists('get_field')) {
    
            update_field( 'organisation', $org, 'user_' . $user_id );
            
            echo " - Organisation: $org<br>";
        }

        if (function_exists('ld_update_group_access')) {
            ld_update_group_access($user_id, $target_group_id);
            echo " - Group: <span style='color:green; font-weight:bold;'>Assigned to $target_group_id</span><br>";
        }
    }
    echo "<hr>";

}

fclose($handle);
echo "<h3>Import Complete. Delete this file immediately.</h3>";
?>
