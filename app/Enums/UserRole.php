<?php

namespace App\Enums;

enum UserRole: string
{
    case Redaktion = 'redaktion';
    case Redakteur = 'redakteur';
    case Administration = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Redakteur => 'Autor*in',
            self::Redaktion => 'Redaktion',
            self::Administration => 'Administration',
        };
    }
}
