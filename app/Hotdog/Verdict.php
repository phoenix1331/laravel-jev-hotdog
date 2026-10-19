<?php

declare(strict_types=1);

namespace App\Hotdog;

enum Verdict: string
{
    case Hotdog = 'hotdog';
    case NotHotdog = 'not_hotdog';
    case Unsure = 'unsure';

    public function label(): string
    {
        return match ($this) {
            self::Hotdog => 'HOTDOG',
            self::NotHotdog => 'NOT HOTDOG',
            self::Unsure => 'NOT SURE',
        };
    }
}
