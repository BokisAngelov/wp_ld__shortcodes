# Useful WordPress and LearnDash shortcodes

## Password Reset Shortcode
This shortcode creates a password reset form for your website.

### How it works:
- User enters username or email - They type their login credentials into the form
- System finds the user - The code searches for a user matching that username or email
- Generates reset link - If found, it creates a special password reset link
- Sends email - The link is emailed to the user
- Shows message - Displays a success or error message
- To use it:
-- Add [password_reset_form] to any WordPress page or post to display the form.

---------------------------------------------------------------------------------------

## Display registered users (LearnDash)
### How it works
- Checks if user is logged in - If not, it stops and shows nothing
- Gathers user data - Pulls all registered users and collects their first name, last name, email, country, and role (adjust the fields if needed)
- Sorts by country - Organizes the list alphabetically by country
- Displays as a table - Creates an HTML table showing all users with their information in rows
-- To use it:
-- Add [display_registered_users] to any WordPress page or post to display the table.

---------------------------------------------------------------------------------------

## Force user import
### How it works
- Reads a CSV file containing user data (username, email, password, name, organization)
- Checks if users already exist by username or email
- Creates new users if they don't exist, or updates existing ones
- Assigns metadata like:
-- Organization field
- Hides the admin toolbar for these users
- Adds users to a LearnDash group (adjust accordingly)
- Shows colored status messages for each row (green for success, red for errors, blue for info)

### Key requirements:
- Admin access only
- CSV file must be in the correct column order
- Requires LearnDash plugin for group assignment
- **Should be deleted immediately after use (for security)**

---------------------------------------------------------------------------------------

## Force login/logout redirect
### How it works
This file does two things:
- Lock Down Your Website
  - If someone is not logged in, kick them to the login page
  - Except: Let them see the homepage, registration page, and password reset page (adjust accordingly)
  - Exception to exception: Don't block AJAX requests or API calls
- Send Users Home After Logout
  - When someone logs out, send them back to the homepage

---------------------------------------------------------------------------------------

## LearnDash custom certificate
### How it works
- On the lesson page — [cardet_lesson_certificate cert_id="1265"]
- The shortcode checks lesson completion and renders either an active download button or a locked one. The download URL it generates looks like:
/certificates/1265/?cardet_cert=1&cert_id=1265&lesson_id=789&cert_nonce=abc123

- The nonce is scoped to cert_id + lesson_id, so it cannot be guessed or reused for another lesson.
- When the user clicks — our template_redirect hook (priority 1, fires before LearnDash) intercepts and:
-- Verifies the nonce
-- Confirms the user is logged in
-- Confirms the lesson is complete
-- Calls learndash_certificate_post_shortcode() which uses LearnDash's TCPDF engine to stream the PDF directly to the browser — using the certificate's featured image as the background, and processing all shortcodes in the template content
  
### One-time WP Admin setup
- Edit the certificate post → in the certificate Settings panel, set page size to Letter and orientation to Landscape
- Add these to the certificate content wherever you need them:
-- [cardet_lesson_title] — outputs the lesson title
-- [cardet_lesson_completed_date] — outputs the date the lesson was completed (in your site's date format from Settings → General)

### Shortcode usage
- [cardet_lesson_certificate cert_id="1265"]
- [cardet_lesson_certificate cert_id="1265" label="Get your badge"]

---------------------------------------------------------------------------------------

## Cloudflare Turnstile Registration Validation
### How it works
- Intercepts user registration - Hooks into the WordPress registration form submission
- Removes default Turnstile validation - Disables the built-in Cloudflare Turnstile plugin's validation to use custom logic
- Retrieves the Turnstile token - Extracts the verification token from the form submission (cf-turnstile-response)
- Validates with Cloudflare - Sends the token to Cloudflare's verification endpoint along with your secret key
- Verifies human status - Checks the response to confirm the user passed the CAPTCHA challenge
- Shows error messages - Displays appropriate error messages if verification fails or is missing
 
### Key requirements:
Your Cloudflare Turnstile keys must be defined in wp-config.php:
- define('CF_TURNSTILE_SITE_KEY', 'xxxxxxxxxx');
- define('CF_TURNSTILE_SECRET_KEY', 'xxxxxxxxxx');


