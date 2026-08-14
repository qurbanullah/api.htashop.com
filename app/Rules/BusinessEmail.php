<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BusinessEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Common free email providers to block
        $freeEmailDomains = [
            'gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'hotmail.co.uk',
            'outlook.com', 'outlook.co.uk', 'aol.com', 'icloud.com', 'me.com',
            'mail.com', 'protonmail.com', 'yandex.com', 'rediffmail.com',
            'zoho.com', 'live.com', 'msn.com', 'mail.ru', '163.com', 'qq.com',
            'sina.com', 'sohu.com', '126.com', 'yeah.net', 'foxmail.com',
            'temp-mail.org', '10minutemail.com', 'guerrillamail.com',
            'mailinator.com', 'tempmail.org'
        ];

        if (empty($value)) {
            return;
        }

        $domain = strtolower(substr(strrchr($value, '@'), 1));

        if (in_array($domain, $freeEmailDomains)) {
            $fail('Please use a business email address. Personal email domains like Gmail, Yahoo, and Hotmail are not allowed.');
        }
    }
}
