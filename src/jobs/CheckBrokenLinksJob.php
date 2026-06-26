<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\queue\BaseJob;
use craigclement\craftbrokenlinks\helpers\UrlSafety;
use craigclement\craftbrokenlinks\records\BrokenLinkRecord;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/**
 * CheckBrokenLinksJob fetches each entry page and HEAD-checks every link found on it.
 */
class CheckBrokenLinksJob extends BaseJob
{
    public int $scanId;
    public array $entryIds = [];
    public string $baseUrl = '';
    public int $totalBatches = 1;
    public int $batchIndex = 0;
    public bool $forceFullScan = false;

    /**
     * Hosts that are always allowed to be requested (the site's own hosts).
     * Populated lazily in execute() since it depends on app config.
     *
     * @var string[]
     */
    private array $allowedHosts = [];

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        // A batch can issue many HEAD requests, each up to ~5s. Give it room.
        return 3600;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $scanRecord = ScanHistoryRecord::findOne($this->scanId);
        if (!$scanRecord) {
            throw new \Exception("Scan history record with ID {$this->scanId} not found.");
        }

        $this->allowedHosts = UrlSafety::siteHosts(Craft::$app->getSites()->getAllSites());

        $client = new Client([
            'timeout' => 5,
            'connect_timeout' => 5,
        ]);
        $brokenLinkCount = 0;
        $visitedUrls = [];

        try {
            $entries = Entry::find()
                ->id($this->entryIds)
                ->all();

            $entryCount = count($entries);
            $currentEntry = 0;

            foreach ($entries as $entry) {
                $currentEntry++;

                $url = $entry->getUrl();

                if (!$url || isset($visitedUrls[$url])) {
                    continue;
                }

                $visitedUrls[$url] = true;

                $brokenLinks = $this->checkPageLinks($client, $url, $entry);
                $brokenLinkCount += count($brokenLinks);

                $batchFraction = $entryCount > 0 ? ($currentEntry / $entryCount) : 1;
                $progress = min(1.0, ($this->batchIndex + $batchFraction) / $this->totalBatches);
                $this->setProgress($queue, $progress);
            }

            $this->finalizeBatch($brokenLinkCount);
        } catch (\Throwable $e) {
            Craft::error('Error checking for broken links: ' . $e->getMessage(), __METHOD__);

            // Mark the scan failed regardless of which batch this is.
            ScanHistoryRecord::updateAll(
                ['status' => ScanHistoryRecord::STATUS_FAILED],
                ['id' => $this->scanId]
            );

            throw $e;
        }
    }

    /**
     * Atomically record this batch's results and mark the scan complete once
     * every batch has finished — regardless of the order they finish in.
     */
    private function finalizeBatch(int $brokenLinkCount): void
    {
        // Atomic increments — safe across concurrent queue workers.
        ScanHistoryRecord::updateAllCounters(
            [
                'totalBrokenLinks' => $brokenLinkCount,
                'completedBatches' => 1,
            ],
            ['id' => $this->scanId]
        );

        $freshScanRecord = ScanHistoryRecord::findOne($this->scanId);
        if (!$freshScanRecord) {
            // The scan was cleared mid-run; nothing left to finalize.
            return;
        }

        if ($freshScanRecord->completedBatches >= $this->totalBatches) {
            // Guard on status so concurrent finishers don't double-complete.
            ScanHistoryRecord::updateAll(
                [
                    'status' => ScanHistoryRecord::STATUS_COMPLETED,
                    'endTime' => Db::prepareDateForDb(new \DateTime()),
                ],
                ['and', ['id' => $this->scanId], ['not', ['status' => ScanHistoryRecord::STATUS_COMPLETED]]]
            );
        }
    }

    private function checkPageLinks(Client $client, string $url, Entry $entry): array
    {
        $brokenLinks = [];
        $entryTitle = $entry->title ?: ($entry->slug ?: 'N/A');

        try {
            $pageResponse = $client->get($url);
            $html = $pageResponse->getBody()->getContents();

            foreach ($this->extractLinks($html) as [$link, $linkText]) {
                $absoluteUrl = $this->resolveUrl($url, $link);

                if (!preg_match('/^https?:\/\//i', $absoluteUrl)) {
                    continue;
                }

                // Block SSRF: skip anything that resolves to a private/reserved
                // address unless it is one of the site's own hosts.
                if (!UrlSafety::isAllowedUrl($absoluteUrl, $this->allowedHosts)) {
                    continue;
                }

                try {
                    $headResponse = $client->head($absoluteUrl, [
                        'allow_redirects' => [
                            'max' => 5,
                            'protocols' => ['http', 'https'],
                            'on_redirect' => function($request, $response, $uri) {
                                if (!UrlSafety::isAllowedUrl((string) $uri, $this->allowedHosts)) {
                                    throw new \RuntimeException('Redirect to a non-public URL was blocked.');
                                }
                            },
                        ],
                    ]);
                    $statusCode = $headResponse->getStatusCode();

                    if ($statusCode >= 400) {
                        $brokenLinks[] = $this->saveBrokenLink([
                            'url' => $absoluteUrl,
                            'status' => 'Broken',
                            'statusCode' => $statusCode,
                            'entryId' => $entry->id,
                            'entryTitle' => $entryTitle,
                            'linkText' => $linkText,
                            'field' => null,
                            'pageUrl' => $url,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $brokenLinks[] = $this->saveBrokenLink([
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'errorMessage' => $e->getMessage(),
                        'entryId' => $entry->id,
                        'entryTitle' => $entryTitle,
                        'linkText' => $linkText,
                        'field' => null,
                        'pageUrl' => $url,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Craft::error("Error crawling page URL: $url - " . $e->getMessage(), __METHOD__);
        }

        return $brokenLinks;
    }

    /**
     * Extract [href, linkText] pairs from a page's HTML using a real parser.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function extractLinks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $links = [];

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // Force UTF-8 handling and suppress the warnings from imperfect markup.
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//a[@href]') as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $links[] = [$href, trim($node->textContent)];
        }

        return $links;
    }

    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        return (string) UriResolver::resolve(
            new Uri($baseUrl),
            new Uri($relativeUrl)
        );
    }

    private function saveBrokenLink(array $data): array
    {
        try {
            $existingRecord = BrokenLinkRecord::find()
                ->where(['url' => $data['url'], 'pageUrl' => $data['pageUrl']])
                ->one();

            if ($existingRecord) {
                $existingRecord->status = $data['status'];
                $existingRecord->statusCode = $data['statusCode'] ?? null;
                $existingRecord->errorMessage = $data['errorMessage'] ?? null;
                $existingRecord->linkText = $data['linkText'];
                $existingRecord->lastScanned = new \DateTime();
                $existingRecord->save();

                return $existingRecord->getAttributes();
            }

            $record = new BrokenLinkRecord();
            $record->url = $data['url'];
            $record->status = $data['status'];
            $record->statusCode = $data['statusCode'] ?? null;
            $record->errorMessage = $data['errorMessage'] ?? null;
            $record->entryId = $data['entryId'];
            $record->entryTitle = $data['entryTitle'];
            $record->linkText = $data['linkText'];
            $record->field = $data['field'];
            $record->pageUrl = $data['pageUrl'];
            $record->lastScanned = new \DateTime();
            $record->save();

            return $record->getAttributes();
        } catch (\Throwable $e) {
            Craft::error('Error saving broken link: ' . $e->getMessage(), __METHOD__);
            return $data;
        }
    }

    protected function defaultDescription(): string
    {
        return 'Checking for broken links';
    }
}
