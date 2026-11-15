<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Minimal DeepSeek chat client (OpenAI-compatible API), called from PHP only
 * so the key never reaches the browser. stream() reads the server-sent
 * events as they arrive and hands every piece of text to $onText, which is
 * how the admin page sees the answer appear bit by bit (the caller writes
 * it to the database, the page polls it: no WebSocket on shared hosting).
 */
class DeepSeekClient
{
    public const URL = 'https://api.deepseek.com/chat/completions';

    /**
     * @param  list<array<string,mixed>>  $messages  OpenAI-style messages (system first)
     * @param  list<array<string,mixed>>  $tools  OpenAI-style function tools ([] = none)
     * @param  callable(string $piece): bool|null  $onText  return false to stop early
     * @return array{content:string,tool_calls:list<array{id:string,name:string,arguments:string}>,finish_reason:?string,usage:?array,stopped:bool}
     */
    public function stream(string $key, string $model, array $messages, array $tools, ?callable $onText = null, int $maxTokens = 4000): array
    {
        $body = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'max_tokens' => $maxTokens,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        $response = Http::withToken($key)
            ->withOptions(['stream' => true])
            ->accept('text/event-stream')
            ->connectTimeout(10)
            ->timeout(150)
            ->post(self::URL, $body);

        if ($response->failed()) {
            $error = json_decode((string) $response->body(), true);
            throw new RuntimeException('DeepSeek HTTP '.$response->status().': '.Str::limit((string) ($error['error']['message'] ?? $response->body()), 200));
        }

        $stream = $response->toPsrResponse()->getBody();

        $content = '';
        $calls = [];
        $finish = null;
        $usage = null;
        $stopped = false;
        $buffer = '';

        $handle = function (string $line) use (&$content, &$calls, &$finish, &$usage, &$stopped, $onText): void {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, 'data:')) {
                return;
            }
            $data = trim(substr($line, 5));
            if ($data === '[DONE]') {
                return;
            }
            $json = json_decode($data, true);
            if (! is_array($json)) {
                return;
            }
            if (! empty($json['usage'])) {
                $usage = $json['usage'];
            }
            $choice = $json['choices'][0] ?? null;
            if (! $choice) {
                return;
            }
            $finish = $choice['finish_reason'] ?? $finish;
            $delta = $choice['delta'] ?? [];

            $piece = (string) ($delta['content'] ?? '');
            if ($piece !== '') {
                $content .= $piece;
                if ($onText && $onText($piece) === false) {
                    $stopped = true;
                }
            }

            foreach ($delta['tool_calls'] ?? [] as $tc) {
                $i = (int) ($tc['index'] ?? 0);
                $calls[$i] ??= ['id' => '', 'name' => '', 'arguments' => ''];
                if (! empty($tc['id'])) {
                    $calls[$i]['id'] = $tc['id'];
                }
                if (! empty($tc['function']['name'])) {
                    $calls[$i]['name'] .= $tc['function']['name'];
                }
                if (isset($tc['function']['arguments'])) {
                    $calls[$i]['arguments'] .= $tc['function']['arguments'];
                }
            }
        };

        while (! $stream->eof() && ! $stopped) {
            $chunk = $stream->read(2048);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $handle(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($stopped) {
                    break;
                }
            }
        }
        if (! $stopped && $buffer !== '') {
            $handle($buffer);
        }
        $stream->close();

        ksort($calls);

        return [
            'content' => $content,
            'tool_calls' => array_values(array_filter($calls, fn ($c) => $c['name'] !== '')),
            'finish_reason' => $finish,
            'usage' => $usage,
            'stopped' => $stopped,
        ];
    }

    /** One short non-streamed reply (settings "Test" button). */
    public function ping(string $key, string $model): string
    {
        $result = $this->stream($key, $model, [
            ['role' => 'system', 'content' => 'You are a test. Reply with one short sentence.'],
            ['role' => 'user', 'content' => 'Say hello in Swahili.'],
        ], [], null, 60);

        return trim($result['content']);
    }
}
