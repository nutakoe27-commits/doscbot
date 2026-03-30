<?php

namespace DocBot;

class TelegramApi
{
    private string $token;
    private bool $sslVerify;

    public function __construct(string $token, bool $sslVerify = false)
    {
        $this->token = $token;
        $this->sslVerify = $sslVerify;
    }

    public function sendMessage(int $chatId, string $text, ?array $keyboard = null, string $parseMode = ''): void
    {
        $params = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        $this->apiRequest('sendMessage', $params);
    }

    public function sendDocument(int $chatId, string $filePath, string $caption = ''): void
    {
        $url = "https://api.telegram.org/bot{$this->token}/sendDocument";

        $post = [
            'chat_id'  => $chatId,
            'document' => new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', basename($filePath)),
        ];
        if ($caption) {
            $post['caption'] = $caption;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $result = curl_exec($ch);
        $this->logApiResult('sendDocument', $result, $ch);
        curl_close($ch);
    }

    public function answerCallback(string $callbackId): void
    {
        $this->apiRequest('answerCallbackQuery', ['callback_query_id' => $callbackId]);
    }

    public function apiRequest(string $method, array $params): ?array
    {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $result = curl_exec($ch);
        $this->logApiResult($method, $result, $ch);
        curl_close($ch);

        return $result ? json_decode($result, true) : null;
    }

    public function logApiResult(string $method, string|false $result, \CurlHandle $ch): void
    {
        if ($result === false) {
            error_log('[DocBot] cURL error in ' . $method . ': ' . curl_error($ch));
            return;
        }
        $decoded = json_decode($result, true);
        if (is_array($decoded) && isset($decoded['ok']) && $decoded['ok'] === false) {
            $desc = $decoded['description'] ?? 'unknown error';
            $code = $decoded['error_code'] ?? 0;
            error_log("[DocBot] Telegram API error in {$method}: [{$code}] {$desc}");
        }
    }
}
