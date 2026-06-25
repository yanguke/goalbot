<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GoalBot Services
    |--------------------------------------------------------------------------
    */

    // Football Data API (API-Football)
    'football' => [
        'key' => env('FOOTBALL_API_KEY'),
        'url' => env('FOOTBALL_API_URL', 'https://v3.football.api-sports.io'),
    ],

    // WhatsApp Business API
    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    ],

    // Anthropic Claude for message generation
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'qa_enabled' => env('AI_QA_ENABLED', true),
    ],

    // OpenAI for message generation (legacy fallback)
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    // Meta (Facebook) Ads + Conversions API
    'meta' => [
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com/v21.0'),
        // Conversions API (signup tracking)
        'pixel_id' => env('META_PIXEL_ID'),
        'capi_token' => env('META_CAPI_TOKEN'),
        'test_event_code' => env('META_TEST_EVENT_CODE'),
        // Marketing API (ads management)
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'business_id' => env('META_BUSINESS_ID'),
        'system_user_token' => env('META_SYSTEM_USER_TOKEN'),
    ],

    // M-Pesa (Kenya mobile payments)
    'mpesa' => [
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
        'shortcode' => env('MPESA_SHORTCODE', '174379'),
        'passkey' => env('MPESA_PASSKEY'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'callback_url' => env('MPESA_CALLBACK_URL'),
    ],

    // Helaplus WhatsApp API wrapper
    'helaplus' => [
        'api_key' => env('HELAPLUS_API_KEY'),
    ],

    // Wasiliana SMS gateway (Africa's Talking-compatible)
    'wasiliana' => [
        'api_key'   => env('WASILIANA_API_KEY'),
        'username'  => env('WASILIANA_USERNAME'),
        'sender_id' => env('WASILIANA_SENDER_ID', 'GoalBot'),
        'sms_url'   => env('WASILIANA_SMS_URL', 'https://api.wasiliana.com/api/developer/v1/messaging/sms/send'),
    ],

];
