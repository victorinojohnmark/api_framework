<?php
namespace Core;

class JWT
{
    /**
     * Generate a JWT Token
     * @param array $payload User data (id, email, etc.)
     * @return string
     */
    public static function encode(array $payload)
    {
        $secret = APP_CONFIG['jwt_secret'];
        
        // 1. Header
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        // 2. Payload
        // Add 'iat' (Issued At) if missing
        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }
        // Add 'exp' (Expiration) if missing (default 1 hour)
        if (!isset($payload['exp'])) {
            $payload['exp'] = time() + 3600; 
        }
        $payloadJson = json_encode($payload);

        // 3. Base64 Url Encode
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payloadJson);

        // 4. Signature
        $signature = hash_hmac('sha256', 
            $base64UrlHeader . "." . $base64UrlPayload, 
            $secret, 
            true
        );
        $base64UrlSignature = self::base64UrlEncode($signature);

        // 5. Combine
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Decode and Validate a JWT Token
     * @param string $token
     * @return object|false Returns payload object if valid, false if invalid
     */
    public static function decode($token)
    {
        $secret = APP_CONFIG['jwt_secret'];
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

        // 1. Re-create the signature to verify
        $signature = hash_hmac('sha256', 
            $base64UrlHeader . "." . $base64UrlPayload, 
            $secret, 
            true
        );
        $validSignature = self::base64UrlEncode($signature);

        // 2. Check Signature
        if (!hash_equals($validSignature, $base64UrlSignature)) {
            return false;
        }

        // 3. Decode Payload
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload));

        // 4. Check Expiration
        if (isset($payload->exp) && $payload->exp < time()) {
            return false; // Token Expired
        }

        return $payload;
    }

    // --- Helpers ---

    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}