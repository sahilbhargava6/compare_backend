<?php

namespace App\Services;

class UrlSanitizerService
{
    /**
     * Clean and strip tracking and affiliate query parameters.
     */
    public function sanitize(string $url): string
    {
        $parsed = parse_url(trim($url));
        if (!$parsed || !isset($parsed['host'])) {
            return $url;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '/';

        // Clean Amazon URLs to canonical /dp/{ASIN} format
        if (str_contains($host, 'amazon.')) {
            if (preg_match('/(?:dp|gp\/product|\/d)\/([A-Z0-9]{10})/i', $path, $matches)) {
                return "{$scheme}://{$host}/dp/{$matches[1]}";
            }
        }

        // Clean query parameters, removing tracking tokens
        $blacklistedParams = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'tag', 'linkCode', 'ref', 'ref_', 'ascsubtag', 'gclid', 'fbclid',
            'affid', 'aff_id', 'aff_sub',
        ];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            foreach ($blacklistedParams as $key) {
                unset($queryParams[$key]);
            }

            $queryString = http_build_query($queryParams);
            return $queryString ? "{$scheme}://{$host}{$path}?{$queryString}" : "{$scheme}://{$host}{$path}";
        }

        return "{$scheme}://{$host}{$path}";
    }
}
