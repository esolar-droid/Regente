<?php
/**
 * reCAPTCHA v3 Configuration
 * 
 * Keys provided by user for regente2.colmarista.com
 */

define('RECAPTCHA_SITE_KEY', '6LduHuQtAAAAAIq9Yqd28-70NSCS2YxPbDdXPha1');
define('RECAPTCHA_SECRET_KEY', '6LduHuQtAAAAAF9XV36f56Qt66zjBz-ajNjebNAI');
define('RECAPTCHA_MIN_SCORE', 0.5); // Minimum score to pass (0.0 to 1.0)

/**
 * Verify reCAPTCHA response
 */
function verifyRecaptcha($token) {
    if (empty($token)) {
        return false;
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("reCAPTCHA verification failed: could not connect to Google");
        return false;
    }
    
    $response = json_decode($result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("reCAPTCHA verification failed: invalid JSON response");
        return false;
    }
    
    if (empty($response['success']) || $response['success'] !== true) {
        error_log("reCAPTCHA verification failed: " . ($response['error-codes'][0] ?? 'unknown error'));
        return false;
    }
    
    if ($response['score'] < RECAPTCHA_MIN_SCORE) {
        error_log("reCAPTCHA verification failed: score too low ({$response['score']} < " . RECAPTCHA_MIN_SCORE . ")");
        return false;
    }
    
    return true;
}
