<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Connected = 'connected';
    case NeedsReauth = 'needs_reauth';
    case Error = 'error';
    case Disconnected = 'disconnected';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Bağlı',
            self::NeedsReauth => 'Yeniden bağlanmalı',
            self::Error => 'Hata',
            self::Disconnected => 'Bağlı değil',
            self::Manual => 'Manuel (API yok)',
        };
    }
}
