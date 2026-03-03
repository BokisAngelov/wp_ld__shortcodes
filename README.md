# Useful WordPress and LearnDash shortcodes


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
