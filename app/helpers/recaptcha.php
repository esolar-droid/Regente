<?php
// reCAPTCHA Helper Functions

if (!function_exists('verifyRecaptcha')) {
    function verifyRecaptcha($response) {
        if (!RECAPTCHA_ENABLED || empty(RECAPTCHA_SECRET_KEY)) {
            return true; // Skip verification if not enabled
        }
        
        if (empty($response)) {
            return false;
        }
        
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents(RECAPTCHA_VERIFY_URL, false, $context);
        
        if ($result === false) {
            error_log("reCAPTCHA verification failed: Could not connect to Google API");
            return true; // Fail open - don't block user if Google API is down
        }
        
        $json = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("reCAPTCHA verification failed: Invalid JSON response");
            return true; // Fail open
        }
        
        // Check if successful
        if (!($json['success'] ?? false)) {
            $errorCodes = $json['error-codes'] ?? [];
            error_log("reCAPTCHA verification failed: " . implode(', ', $errorCodes));
            return false;
        }
        
        // For reCAPTCHA v3, check score
        if (isset($json['score']) && $json['score'] < RECAPTCHA_MIN_SCORE) {
            error_log("reCAPTCHA verification failed: Score too low ({$json['score']})");
            return false;
        }
        
        return true;
    }
}

if (!function_exists('getRecaptchaSiteKey')) {
    function getRecaptchaSiteKey() {
        return RECAPTCHA_ENABLED ? RECAPTCHA_SITE_KEY : '';
    }
}

if (!function_exists('isRecaptchaEnabled')) {
    function isRecaptchaEnabled() {
        return RECAPTCHA_ENABLED && !empty(RECAPTCHA_SITE_KEY) && !empty(RECAPTCHA_SECRET_KEY);
    }
}
