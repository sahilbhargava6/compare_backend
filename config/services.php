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

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
        'index' => env('PINECONE_INDEX', 'products'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'), // openai or gemini
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
        ],
    ],

    'scrapers' => [
        'tier1' => [
            'amazon_paapi' => [
                'access_key' => env('AMAZON_PAAPI_ACCESS_KEY'),
                'secret_key' => env('AMAZON_PAAPI_SECRET_KEY'),
                'partner_tag' => env('AMAZON_PAAPI_PARTNER_TAG'),
                'region' => env('AMAZON_PAAPI_REGION', 'IN'),
                'host' => env('AMAZON_PAAPI_HOST', 'webservices.amazon.in'),
            ],
            'flipkart' => [
                'affiliate_id' => env('FLIPKART_AFFILIATE_ID'),
                'token' => env('FLIPKART_AFFILIATE_TOKEN'),
            ],
        ],
        'tier2' => [
            'rainforest' => [
                'api_key' => env('RAINFOREST_API_KEY'),
            ],
            'apify' => [
                'api_token' => env('APIFY_API_TOKEN'),
                'nykaa_actor_id' => env('APIFY_NYKAA_ACTOR_ID', 'apify/web-scraper'),
            ],
        ],
        'tier3' => [
            'scraper_api' => [
                'api_key' => env('SCRAPER_API_KEY'),
            ],
        ],
    ],

];
