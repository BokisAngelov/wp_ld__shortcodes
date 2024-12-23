<?php
function display_registered_users() {

        if (!is_user_logged_in()){
            return;
        }
        // Get all users
        $users = get_users();
        $user_data = [];
    
        // Loop through each user and gather their information
        foreach ($users as $user) {
            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            $email = $user->user_email;
            $country = get_field('user_country', 'user_' . $user->ID); // ACF field for country
            $role = get_field('role', 'user_' . $user->ID); // ACF field for country
    
            // Store the user data in an array for sorting
            $user_data[] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'country' => $country['label'],
                'role' => $role
            ];
        }
    
        // Sort the users by the country field
        usort($user_data, function($a, $b) {
            return strcmp($a['country'], $b['country']);
        });
    
        // Start the output with a table
        $output = '<table class="uk-text-center">';
        $output .= '<tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Role</th><th>Country</th></tr>';
    
        // Loop through the sorted user data to build the table rows
        foreach ($user_data as $user) {
            $output .= '<tr>';
            $output .= '<td>' . esc_html($user['first_name']) . '</td>';
            $output .= '<td>' . esc_html($user['last_name']) . '</td>';
            $output .= '<td>' . esc_html($user['email']) . '</td>';
            $output .= '<td>' . esc_html($user['role']) . '</td>';
            $output .= '<td>' . esc_html($user['country']) . '</td>';
            $output .= '</tr>';
        }
    
        $output .= '</table>';
   
        return $output;
    }

?>
