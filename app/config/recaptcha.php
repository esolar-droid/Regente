<?php
// reCAPTCHA Configuration
// To use reCAPTCHA, you need to:
// 1. Register your domain at https://www.google.com/recaptcha/admin
// 2. Get your Site Key and Secret Key
// 3. Set RECAPTCHA_ENABLED to true

// Enable/disable reCAPTCHA
define('RECAPTCHA_ENABLED', false);

// reCAPTCHA v3 Keys (get from Google reCAPTCHA admin)
define('RECAPTCHA_SITE_KEY', '');  // Your site key
define('RECAPTCHA_SECRET_KEY', ''); // Your secret key

// reCAPTCHA v2 Keys (alternative)
// define('RECAPTCHA_SITE_KEY', '');
// define('RECAPTCHA_SECRET_KEY', '');

// Minimum score for reCAPTCHA v3 (0.0 to 1.0)
define('RECAPTCHA_MIN_SCORE', 0.5);

// reCAPTCHA API endpoint
define('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');
