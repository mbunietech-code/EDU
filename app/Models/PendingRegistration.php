<?php

namespace App\Models;

use App\Notifications\Auth\EmailVerificationCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class PendingRegistration extends Model
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'verification_code',
        'verification_code_expires_at',
    ];

    protected $hidden = [
        'password',
        'verification_code',
    ];

    protected function casts(): array
    {
        return [
            'verification_code_expires_at' => 'datetime',
        ];
    }

    public function routeNotificationForMail($notification = null): string
    {
        return $this->email;
    }

    public function refreshVerificationCode(): void
    {
        $expiresInMinutes = 15;
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes($expiresInMinutes),
        ])->save();

        $this->notify(new EmailVerificationCode($code, $expiresInMinutes));
    }

    public function hasValidVerificationCode(string $code): bool
    {
        $code = preg_replace('/\D+/', '', $code);

        return $this->verification_code !== null
            && hash_equals($this->verification_code, $code)
            && $this->verification_code_expires_at?->isFuture();
    }
}
