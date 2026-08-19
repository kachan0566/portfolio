<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class BusinessDate
{
    public static function now(): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'Asia/Tokyo');
        $fixedDate = config('business.fixed_date');

        if (is_string($fixedDate) && $fixedDate !== '') {
            return CarbonImmutable::createFromFormat('!Y-m-d', $fixedDate, $timezone);
        }

        return CarbonImmutable::now($timezone);
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }

    public static function currentYm(): string
    {
        return self::now()->format('Y-m');
    }
}
