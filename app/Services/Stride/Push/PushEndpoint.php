<?php

namespace App\Services\Stride\Push;

use Illuminate\Validation\ValidationException;

final class PushEndpoint
{
    private const ALLOWED_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        'web.push.apple.com',
    ];

    /** Edge on Windows uses regional hosts like wns2-par02p.notify.windows.com. */
    private const WINDOWS_HOST_SUFFIX = '.notify.windows.com';

    public static function validate(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');

        $allowedHost = in_array($host, self::ALLOWED_HOSTS, true) || str_ends_with($host, self::WINDOWS_HOST_SUFFIX);

        if (($parts['scheme'] ?? null) !== 'https'
            || ! $allowedHost
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || isset($parts['fragment'])
            || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw ValidationException::withMessages(['subscription.endpoint' => ['The push endpoint is not supported.']]);
        }

        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw ValidationException::withMessages(['subscription.endpoint' => ['The push endpoint is not public.']]);
            }
        }

        return hash('sha256', $endpoint);
    }
}
