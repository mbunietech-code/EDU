<?php

namespace App\Exceptions\Learning;

/**
 * Thrown by RoomService::join() when a viewer who may see the room still
 * cannot enter the call right now. $reason is machine-readable so the
 * classroom page and the API can pick the right screen:
 *   not_live — the session has not started or has already ended
 *   removed  — the host removed this user from the current session
 *   inactive — the account is suspended / not active
 *   locked   — the host locked the room and this user was not in it yet
 */
class RoomAccessException extends \RuntimeException
{
    public function __construct(public string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($reason));
    }

    private static function defaultMessage(string $reason): string
    {
        return match ($reason) {
            'not_live' => 'This live session is not running right now.',
            'removed' => 'The host removed you from this session.',
            'inactive' => 'Your account is not active.',
            'locked' => 'The host has locked this class. Only people who were already in it can rejoin.',
            default => 'You cannot join this session.',
        };
    }
}
