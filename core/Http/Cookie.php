<?php
namespace Core\Http;

class Cookie
{
    /**
     * Set a secure HttpOnly cookie
     * @param string $name
     * @param string $value
     * @param int $minutes Duration in minutes
     */
    public static function set($name, $value, $minutes = 60, $domain = null)
    {
        $expiry = time() + ($minutes * 60);
        $path = '/';
        
        // AUTO-DETECT DOMAIN
        // If $domain is null, we try to set it to the top-level domain of the current request
        // e.g., request to "api.client-site.com" -> cookie domain ".client-site.com"
        if ($domain === null) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            // Remove port if present
            $host = explode(':', $host)[0]; 
            
            // Simple logic: if it's not localhost/IP, prepend dot
            if (!in_array($host, ['localhost', '127.0.0.1']) && !filter_var($host, FILTER_VALIDATE_IP)) {
                // Strip subdomains (simplistic approach: take last two parts)
                // "api.client-site.com" -> ".client-site.com"
                $parts = explode('.', $host);
                if (count($parts) > 1) {
                    $domain = '.' . implode('.', array_slice($parts, -2));
                }
            }
        }

        // Security Flags
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httpOnly = true;
        $sameSite = 'Lax'; 

        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, [
                'expires' => $expiry,
                'path' => $path,
                'domain' => $domain, // Dynamic!
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite
            ]);
        } else {
            setcookie($name, $value, $expiry, $path, $domain, $secure, $httpOnly);
        }
    }

    public static function get($name)
    {
        return $_COOKIE[$name] ?? null;
    }

    public static function forget($name)
    {
        self::set($name, '', -60); // Set expiry in the past
        unset($_COOKIE[$name]);
    }
}