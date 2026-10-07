<?php
/**
 * reCAPTCHA Configuration
 * 
 * Update these values with your actual reCAPTCHA keys
 */

// Enable/disable reCAPTCHA
define('RECAPTCHA_ENABLED', true);

// reCAPTCHA v3 Keys for regente2.colmarista.com
define('RECAPTCHA_SITE_KEY', '6LduHuQtAAAAAIq9Yqd28-70NSCS2YxPbDdXPha1');
define('RECAPTCHA_SECRET_KEY', '6LduHuQtAAAAAF9XV36f56Qt66zjBz-ajNjebNAI');

// reCAPTCHA API endpoint
define('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');

// Minimum score for reCAPTCHA v3 (0.0 to 1.0)
define('RECAPTCHA_MIN_SCORE', 0.5);
