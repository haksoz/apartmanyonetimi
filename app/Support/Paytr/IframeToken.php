<?php

namespace App\Support\Paytr;

final class IframeToken
{
    public static function valid(mixed $token): bool
    {
        // Provider tokens are opaque, not merchant_oid values. Encode as one URL path segment.
        return is_string($token) && strlen($token) >= 1 && strlen($token) <= 2048
            && ! in_array($token, ['.', '..'], true)
            && (bool) preg_match('/^[\x21-\x7E]+$/D', $token);
    }
}
