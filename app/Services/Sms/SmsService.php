<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wasiliana SMS gateway (Africa's Talking-compatible format).
 * Uses the same AT API contract: form POST, apiKey header, SMSMessageData response.
 */
class SmsService
{
    private string $apiKey;
    private string $username;
    private string $senderId;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey   = (string) config('services.wasiliana.api_key', '');
        $this->username = (string) config('services.wasiliana.username', '');
        $this->senderId = (string) config('services.wasiliana.sender_id', 'GoalBot');
        $this->apiUrl   = (string) config('services.wasiliana.sms_url', 'https://api.wasiliana.com/api/developer/v1/messaging/sms/send');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->username);
    }

    /**
     * Send an SMS. Returns true on success.
     */
    public function send(string $to, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('SmsService: Wasiliana not configured, skipping SMS', ['to' => $to]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->asForm()->post($this->apiUrl, [
                'username' => $this->username,
                'to'       => $this->formatPhone($to),
                'message'  => $message,
                'from'     => $this->senderId,
            ]);

            $body = $response->json();

            // AT-compatible success: statusCode 101 = success
            $statusCode = $body['SMSMessageData']['Recipients'][0]['statusCode'] ?? null;
            if ($response->successful() && $statusCode === 101) {
                Log::info('SMS sent via Wasiliana', ['to' => $to]);
                return true;
            }

            Log::warning('Wasiliana SMS failed', ['to' => $to, 'response' => $body]);
            return false;

        } catch (\Throwable $e) {
            Log::error('Wasiliana SMS exception', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function formatPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '254' . substr($cleaned, 1);
        }

        return '+' . ltrim($cleaned, '+');
    }
}
