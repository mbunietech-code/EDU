<?php

namespace App\Services\Learning;

use App\Models\LearningRoom;
use App\Models\User;
use App\Support\Jwt;

/**
 * The live video provider behind every classroom: Jitsi Meet (public
 * meet.jit.si demo or a self-hosted server with HS256 token auth) or JaaS
 * (8x8.vc, RS256 tokens). Chosen by config('learning.live.provider').
 *
 * The browser embeds the provider through the official IFrame API
 * (JitsiMeetExternalAPI) loaded from scriptUrl(); this class produces
 * everything the page needs to do so.
 */
class LiveProvider
{
    public const PUBLIC_JITSI_DOMAIN = 'meet.jit.si';

    public const JAAS_DOMAIN = '8x8.vc';

    public const DEMO_WARNING = 'Embedded meet.jit.si calls are limited to 5 minutes and require a moderator login — use JaaS or a self-hosted Jitsi in production';

    /** Accepted clock drift between 8x8 and us when checking a webhook timestamp. */
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    /** Tokens become valid slightly in the past so a client clock running behind is not rejected. */
    private const TOKEN_LEEWAY_SECONDS = 10;

    /** Configured provider key: 'jitsi' | 'jaas'. */
    public function name(): string
    {
        return config('learning.live.provider') === 'jaas' ? 'jaas' : 'jitsi';
    }

    /** Human label for the settings/status panel, e.g. "Jitsi Meet (meet.jit.si)". */
    public function label(): string
    {
        if ($this->isJaas()) {
            return 'Jitsi as a Service (8x8.vc)';
        }

        return 'Jitsi Meet ('.$this->domain().')';
    }

    /** Host the IFrame API connects to (jitsi domain, or '8x8.vc' for JaaS). */
    public function domain(): string
    {
        if ($this->isJaas()) {
            return self::JAAS_DOMAIN;
        }

        // Tolerate "https://meet.example.com/" in the env value.
        $domain = trim((string) config('learning.live.jitsi.domain'));
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain);
        $domain = strtolower(rtrim((string) $domain, '/'));

        return $domain !== '' ? $domain : self::PUBLIC_JITSI_DOMAIN;
    }

    /** https://{domain}/external_api.js, or https://8x8.vc/{appId}/external_api.js for JaaS. */
    public function scriptUrl(): string
    {
        if ($this->isJaas()) {
            return 'https://'.self::JAAS_DOMAIN.'/'.rawurlencode($this->jaasAppId()).'/external_api.js';
        }

        return 'https://'.$this->domain().'/external_api.js';
    }

    /** Conference name: the room's provider_room, prefixed "{appId}/" for JaaS. */
    public function roomName(LearningRoom $room): string
    {
        return $this->isJaas()
            ? $this->jaasAppId().'/'.$room->provider_room
            : (string) $room->provider_room;
    }

    /** True on the public meet.jit.si service (embedded calls are time-limited there). */
    public function isDemo(): bool
    {
        return ! $this->isJaas() && $this->domain() === self::PUBLIC_JITSI_DOMAIN;
    }

    /** True when joins carry a signed JWT (JaaS, or self-hosted Jitsi with app_id + app_secret). */
    public function usesJwt(): bool
    {
        if ($this->isJaas()) {
            return true;
        }

        // meet.jit.si only accepts its own tokens, so app credentials are ignored there.
        return ! $this->isDemo() && $this->jitsiAppId() !== '' && $this->jitsiAppSecret() !== '';
    }

    /** Server-side recording is available (JaaS, or self-hosted Jitsi with Jibri enabled in config). */
    public function supportsRecording(): bool
    {
        if ($this->isJaas()) {
            return true;
        }

        return ! $this->isDemo() && (bool) config('learning.live.jitsi.recording');
    }

    /**
     * Signed join token for this user and room, or null when the provider
     * does not use JWTs. Lifetime: config('learning.live.token_ttl_minutes').
     *
     * @throws \RuntimeException when JaaS is selected but its credentials are incomplete or unusable
     */
    public function token(User $user, LearningRoom $room, bool $moderator): ?string
    {
        if (! $this->usesJwt()) {
            return null;
        }

        $now = now()->getTimestamp();
        $times = [
            'exp' => $now + max(1, (int) config('learning.live.token_ttl_minutes', 240)) * 60,
            'nbf' => $now - self::TOKEN_LEEWAY_SECONDS,
        ];

        if (! $this->isJaas()) {
            $appId = $this->jitsiAppId();

            return Jwt::encode([
                'aud' => $appId,
                'iss' => $appId,
                'sub' => $this->domain(),
                'room' => (string) $room->provider_room,
                ...$times,
                'context' => [
                    'user' => [...$this->userClaims($user), 'moderator' => $moderator],
                    'features' => ['recording' => $moderator && $this->supportsRecording()],
                ],
                'moderator' => $moderator,
            ], $this->jitsiAppSecret(), 'HS256');
        }

        $issues = $this->jaasIssues();

        if ($issues !== []) {
            throw new \RuntimeException('JaaS live classes are not configured: '.implode(' ', $issues));
        }

        // JaaS expects the boolean-looking claims as strings.
        return Jwt::encode([
            'aud' => 'jitsi',
            'iss' => 'chat',
            'sub' => $this->jaasAppId(),
            'room' => (string) $room->provider_room,
            ...$times,
            'context' => [
                'user' => [...$this->userClaims($user), 'moderator' => $moderator ? 'true' : 'false'],
                'features' => [
                    'livestreaming' => 'false',
                    'recording' => $moderator ? 'true' : 'false',
                    'transcription' => 'false',
                    'outbound-call' => 'false',
                ],
            ],
        ], (string) $this->jaasPrivateKey(), 'RS256', ['kid' => $this->jaasKeyId()]);
    }

    /**
     * Everything learnClassroom() needs to mount the IFrame:
     * ['provider','domain','scriptUrl','roomName','jwt','isDemo','supportsRecording','moderator',
     *  'user' => ['id','name','email'], 'configOverwrite' => [...], 'interfaceConfigOverwrite' => [...]].
     *
     * @return array<string,mixed>
     */
    public function clientConfig(User $user, LearningRoom $room, bool $moderator): array
    {
        $toolbar = $this->toolbarButtons($room, $moderator);

        return [
            'provider' => $this->name(),
            'domain' => $this->domain(),
            'scriptUrl' => $this->scriptUrl(),
            'roomName' => $this->roomName($room),
            'jwt' => $this->token($user, $room, $moderator),
            'isDemo' => $this->isDemo(),
            'supportsRecording' => $this->supportsRecording(),
            'moderator' => $moderator,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'configOverwrite' => [
                'prejoinConfig' => ['enabled' => false],
                'disableDeepLinking' => true,
                'subject' => $room->title,
                'startWithAudioMuted' => ! $moderator,
                'startWithVideoMuted' => ! $moderator,
                'disableInviteFunctions' => true,
                'toolbarButtons' => $toolbar,
            ],
            // Older self-hosted deployments still read the toolbar from interfaceConfig.
            'interfaceConfigOverwrite' => [
                'TOOLBAR_BUTTONS' => $toolbar,
                'MOBILE_APP_PROMO' => false,
                'SHOW_CHROME_EXTENSION_BANNER' => false,
                'HIDE_INVITE_MORE_HEADER' => true,
            ],
        ];
    }

    /**
     * Health summary for the admin/studio screens:
     * ['provider','label','domain','configured'=>bool,'recording'=>bool,'demo'=>bool,'issues'=>list<string>].
     *
     * @return array<string,mixed>
     */
    public function status(): array
    {
        $issues = [];
        $configured = true;

        $raw = (string) config('learning.live.provider');
        if (! in_array($raw, ['jitsi', 'jaas'], true)) {
            $issues[] = 'Unknown live provider "'.$raw.'" (LEARNING_LIVE_PROVIDER) — falling back to Jitsi Meet.';
        }

        if ($this->isJaas()) {
            $jaasIssues = $this->jaasIssues();
            $configured = $jaasIssues === [];
            array_push($issues, ...$jaasIssues);

            if ((string) config('learning.live.jaas.webhook_secret') === '') {
                $issues[] = 'LEARNING_JAAS_WEBHOOK_SECRET is not set — JaaS cloud recordings will not be imported.';
            }
        } elseif ($this->isDemo()) {
            $issues[] = self::DEMO_WARNING;

            if ($this->jitsiAppId() !== '' || $this->jitsiAppSecret() !== '') {
                $issues[] = 'meet.jit.si does not accept your own tokens — LEARNING_JITSI_APP_ID / LEARNING_JITSI_APP_SECRET are ignored.';
            }

            if ((bool) config('learning.live.jitsi.recording')) {
                $issues[] = 'Server recording is not available on meet.jit.si — LEARNING_JITSI_RECORDING is ignored.';
            }
        } elseif (($this->jitsiAppId() === '') !== ($this->jitsiAppSecret() === '')) {
            $configured = false;
            $issues[] = 'Token auth needs both LEARNING_JITSI_APP_ID and LEARNING_JITSI_APP_SECRET — only one is set.';
        }

        return [
            'provider' => $this->name(),
            'label' => $this->label(),
            'domain' => $this->domain(),
            'configured' => $configured,
            'recording' => $this->supportsRecording(),
            'demo' => $this->isDemo(),
            'issues' => $issues,
        ];
    }

    /**
     * Verify an "X-Jaas-Signature: t=<unix>,v1=<base64 hmac>" header: HMAC-SHA256
     * of "t.rawBody" with the webhook secret, constant-time compare, and a
     * ±300 s timestamp window.
     */
    public function verifyJaasWebhook(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) config('learning.live.jaas.webhook_secret');

        if ($secret === '' || $signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            // Split on the first "=" only — base64 values end in "=" padding.
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't') {
                $timestamp = $value;
            } elseif ($name === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret, true));

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    // --- Internals ---------------------------------------------------
    private function isJaas(): bool
    {
        return $this->name() === 'jaas';
    }

    /** @return list<string> */
    private function toolbarButtons(LearningRoom $room, bool $moderator): array
    {
        if ($moderator) {
            $buttons = ['camera', 'microphone', 'desktop', 'participants-pane', 'raisehand', 'tileview', 'fullscreen', 'settings'];

            if ($this->supportsRecording()) {
                $buttons[] = 'recording';
            }

            return $buttons;
        }

        $media = $room->allow_participant_media ? ['camera', 'microphone', 'desktop'] : [];

        return [...$media, 'raisehand', 'tileview', 'fullscreen', 'settings'];
    }

    /** @return array{id:string,name:string,email:string,avatar:string} */
    private function userClaims(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'avatar' => '',
        ];
    }

    private function jitsiAppId(): string
    {
        return trim((string) config('learning.live.jitsi.app_id'));
    }

    private function jitsiAppSecret(): string
    {
        return (string) config('learning.live.jitsi.app_secret');
    }

    private function jaasAppId(): string
    {
        return trim((string) config('learning.live.jaas.app_id'));
    }

    private function jaasKeyId(): string
    {
        return trim((string) config('learning.live.jaas.api_key_id'));
    }

    /**
     * The JaaS RSA private key: the env string (with literal "\n" sequences
     * turned back into newlines) or, failing that, the contents of the
     * configured file (relative paths resolve from the project root).
     */
    private function jaasPrivateKey(): ?string
    {
        $inline = trim((string) config('learning.live.jaas.private_key'));

        if ($inline !== '') {
            return str_replace(['\r\n', '\n'], "\n", trim($inline, "\"' \t\r\n"));
        }

        $path = $this->jaasPrivateKeyPath();

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false || trim($contents) === '' ? null : $contents;
    }

    private function jaasPrivateKeyPath(): ?string
    {
        $path = trim((string) config('learning.live.jaas.private_key_path'));

        if ($path === '') {
            return null;
        }

        $absolute = preg_match('#^([a-z]:[\\\\/]|[\\\\/])#i', $path) === 1;

        return $absolute ? $path : base_path($path);
    }

    /**
     * Why a JaaS token cannot be issued right now (empty when it can).
     *
     * @return list<string>
     */
    private function jaasIssues(): array
    {
        $issues = [];

        if ($this->jaasAppId() === '') {
            $issues[] = 'LEARNING_JAAS_APP_ID is not set.';
        }

        if ($this->jaasKeyId() === '') {
            $issues[] = 'LEARNING_JAAS_API_KEY_ID is not set.';
        } elseif ($this->jaasAppId() !== '' && ! str_starts_with($this->jaasKeyId(), $this->jaasAppId().'/')) {
            $issues[] = 'LEARNING_JAAS_API_KEY_ID must be the full key id ("'.$this->jaasAppId().'/…").';
        }

        $key = $this->jaasPrivateKey();

        if ($key === null) {
            $path = $this->jaasPrivateKeyPath();
            $issues[] = $path === null
                ? 'No JaaS private key: set LEARNING_JAAS_PRIVATE_KEY or LEARNING_JAAS_PRIVATE_KEY_PATH.'
                : 'The JaaS private key file ('.$path.') is missing or not readable.';
        } else {
            $parsed = openssl_pkey_get_private($key);

            if ($parsed === false || (openssl_pkey_get_details($parsed)['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
                while (openssl_error_string() !== false) {
                    // drain OpenSSL's error queue
                }
                $issues[] = 'The JaaS private key is not a valid PEM RSA private key.';
            }
        }

        return $issues;
    }
}
