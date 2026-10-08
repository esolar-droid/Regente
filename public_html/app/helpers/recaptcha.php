<?php
/**
 * reCAPTCHA Helper
 */

if (!function_exists('verifyRecaptcha')) {
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
}

if (!function_exists('getRecaptchaSiteKey')) {
    function getRecaptchaSiteKey() {
        return RECAPTCHA_SITE_KEY;
    }
}
