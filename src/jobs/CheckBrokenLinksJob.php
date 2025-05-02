<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Client;

class CheckBrokenLinksJob extends BaseJob
{
    public array $urls = [];

    public function execute($queue): void
    {
        $client = new Client(['timeout' => 5]);
        $brokenLinks = [];

        $batchSize = \craigclement\craftbrokenlinks\Plugin::getInstance()->getSettings()->batchSize;
        $urls = array_chunk($this->urls, $batchSize);

        Craft::info("Received URLs for broken link checking: " . json_encode($this->urls), __METHOD__);

        foreach ($urls as $urlBatch) {
            foreach ($urlBatch as $url) {
                Craft::info("Fetching URL content: $url", __METHOD__);

                try {
                    // Fetch page content
                    $response = $client->get($url);
                    $html = $response->getBody()->getContents();

                    // Extract all <a> links from the page
                    preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)".*?>(.*?)<\/a>/is', $html, $matches);
                    $foundUrls = $matches[1] ?? [];
                    $linkTexts = $matches[2] ?? [];

                    foreach ($foundUrls as $index => $link) {
                        $absoluteUrl = $this->resolveUrl($url, $link);
                        $linkText = strip_tags(trim($linkTexts[$index] ?? ''));

                        // Skip non-http/https links
                        if (!preg_match('/^https?:\/\//', $absoluteUrl)) {
                            continue;
                        }

                        try {
                            $linkResponse = $client->head($absoluteUrl);
                            if ($linkResponse->getStatusCode() >= 400) {
                                $brokenLinks[] = [
                                    'url' => $absoluteUrl,
                                    'status' => 'Broken (' . $linkResponse->getStatusCode() . ')',
                                    'pageUrl' => $url,
                                    'linkText' => $linkText,
                                ];
                                Craft::info("❌ Broken link detected: $absoluteUrl", __METHOD__);
                            }
                        } catch (\Throwable $e) {
                            $brokenLinks[] = [
                                'url' => $absoluteUrl,
                                'status' => 'Unreachable',
                                'error' => $e->getMessage(),
                                'pageUrl' => $url,
                                'linkText' => $linkText,
                            ];
                            Craft::info("🚨 Unreachable link detected: $absoluteUrl", __METHOD__);
                        }
                    }
                } catch (\Throwable $e) {
                    Craft::error("Error checking URL: $url - " . $e->getMessage(), __METHOD__);
                }
            }
        }

        // Merge with existing cached results
        $existing = Craft::$app->cache->get('brokenLinks_results');
        if (!is_array($existing)) {
            $existing = [];
        }
        
        $merged = array_merge($existing, $brokenLinks);
        Craft::$app->cache->set('brokenLinks_results', $merged, 3600);
    }

    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        return (string) \GuzzleHttp\Psr7\UriResolver::resolve(
            new \GuzzleHttp\Psr7\Uri($baseUrl),
            new \GuzzleHttp\Psr7\Uri($relativeUrl)
        );
    }

    protected function defaultDescription(): string
    {
        return 'Checking batch of links for broken URLs';
    }
}
