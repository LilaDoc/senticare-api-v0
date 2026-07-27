<?php

namespace App\Enum;

/**
 * Statut d'envoi d'une notification (CDC §5.1).
 */
enum NotificationStatusEnum: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Envoyée',
            self::Failed => 'Échec',
        };
    }
}
