<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageSender
{
    private string $apiUrl;
    private string $accessToken;
    private string $phoneNumberId;
    
    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }
    
    /**
     * Send a simple text message (within 24h window)
     */
    public function sendText(string $to, string $message): bool
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);
            
            if ($response->successful()) {
                Log::info('WhatsApp message sent', ['to' => $to]);
                return true;
            }
            
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'error' => $response->json(),
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('WhatsApp exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Send using a template (for notifications outside 24h window)
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = []): bool
    {
        try {
            $components = [];
            
            if (!empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => collect($parameters)->map(fn($p) => [
                        'type' => 'text',
                        'text' => $p,
                    ])->toArray(),
                ];
            }
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en'],
                ],
            ];
            
            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }
            
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);
            
            if ($response->successful()) {
                Log::info('WhatsApp template sent', ['to' => $to, 'template' => $templateName]);
                return true;
            }
            
            Log::error('WhatsApp template failed', [
                'to' => $to,
                'template' => $templateName,
                'error' => $response->json(),
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('WhatsApp template exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Send match alert using pre-approved template
     */
    public function sendAlert(string $to, string $message): bool
    {
        // Use template for guaranteed delivery
        return $this->sendTemplate($to, 'match_alert', [$message]);
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber(string $number): string
    {
        // Remove spaces, dashes, and plus
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        
        // Add country code if missing (default to Kenya 254)
        if (strlen($cleaned) <= 10 && !str_starts_with($cleaned, '254')) {
            $cleaned = '254' . ltrim($cleaned, '0');
        }
        
        return $cleaned;
    }
    
    /**
     * Send interactive buttons message
     */
    public function sendInteractiveButtons(string $to, string $header, string $body, string $footer, array $buttons): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => $header
                ],
                'body' => [
                    'text' => $body
                ],
                'footer' => [
                    'text' => $footer
                ],
                'action' => [
                    'buttons' => $buttons
                ]
            ]
        ];
        
        try {
            $response = $this->client->post("{$this->baseUrl}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);
            
            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true)
            ];
        } catch (GuzzleException $e) {
            Log::error('WhatsApp interactive buttons send failed', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify webhook subscription
     */
    public function verifyWebhook(string $verifyToken, string $challenge): ?string
    {
        $expectedToken = config('services.whatsapp.verify_token');
        
        if ($verifyToken === $expectedToken) {
            return $challenge;
        }
        
        return null;
    }
}
