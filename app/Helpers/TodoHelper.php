<?php

namespace App\Helpers;

class TodoHelper
{
    /**
     * Adres van de takenapp. Draait hij op een eigen domein (TODO_DOMAIN),
     * dan daarheen; anders onder /todo op het CRM-domein.
     */
    public static function url(string $pad = ''): string
    {
        $pad = ltrim($pad, '/');

        if ($domein = config('todo.domain')) {
            return rtrim('https://' . $domein . '/' . $pad, '/');
        }

        return rtrim(url('/todo/' . $pad), '/');
    }
}
