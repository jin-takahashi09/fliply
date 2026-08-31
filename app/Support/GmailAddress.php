<?php

namespace App\Support;

class GmailAddress
{
    /**
     * @var list<string>
     */
    private const DOMAINS = [
        '@gmail.com',
        '@googlemail.com',
    ];

    public static function isGmailAddress(string $email): bool
    {
        $atPosition = strrpos($email, '@');

        if ($atPosition === false) {
            return false;
        }

        $domain = strtolower(substr($email, $atPosition));

        return in_array($domain, self::DOMAINS, true);
    }
}
