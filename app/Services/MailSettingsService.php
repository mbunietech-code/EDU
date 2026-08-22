<?php

namespace App\Services;

use App\Models\Setting;

class MailSettingsService
{
    public function __construct(protected CredentialService $credentialService)
    {
    }

    public function apply(): void
    {
        $settings = Setting::where('group', 'mail')->pluck('value', 'key');

        if ($settings->isEmpty()) {
            return;
        }

        if ($settings->get('mail_mailer')) {
            config(['mail.default' => $settings->get('mail_mailer')]);
        }

        if ($settings->get('mail_host')) {
            config(['mail.mailers.smtp.host' => $settings->get('mail_host')]);
        }

        if ($settings->get('mail_port')) {
            config(['mail.mailers.smtp.port' => (int) $settings->get('mail_port')]);
        }

        if ($settings->has('mail_encryption')) {
            config(['mail.mailers.smtp.encryption' => $settings->get('mail_encryption') ?: null]);
        }

        if ($settings->get('mail_username')) {
            config(['mail.mailers.smtp.username' => $settings->get('mail_username')]);
        }

        if ($settings->get('mail_password')) {
            $password = $this->credentialService->decryptValue($settings->get('mail_password'));

            if ($password !== null) {
                config(['mail.mailers.smtp.password' => $password]);
            }
        }

        if ($settings->get('mail_from_address')) {
            config(['mail.from.address' => $settings->get('mail_from_address')]);
        }

        if ($settings->get('mail_from_name')) {
            config(['mail.from.name' => $settings->get('mail_from_name')]);
        }
    }
}
