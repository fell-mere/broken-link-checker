<?php

namespace craigclement\craftbrokenlinks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use GuzzleHttp\Client;
use craigclement\craftbrokenlinks\records\ScanHistoryRecord;
use craigclement\craftbrokenlinks\records\BrokenLinkRecord;

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

    public function execute($queue): void
    {
        $scanRecord = ScanHistoryRecord::findOne($this->scanId);
        if (!$scanRecord) {
            throw new \Exception("Scan history record with ID {$this->scanId} not found.");
        }

        $client = new Client(['timeout' => 5, 'connect_timeout' => 5]);
        $brokenLinkCount = 0;
        $visitedUrls = [];

        try {
            $entries = Entry::find()
                ->id($this->entryIds)
                ->all();

            $entryCount = count($entries);
            $currentEntry = 0;

            foreach ($entries as $entry) {
                $url = $entry->getUrl();

                if (!$url || isset($visitedUrls[$url])) {
                    continue;
                }

                $visitedUrls[$url] = true;

                $brokenLinks = $this->checkPageLinks($client, $url, $entry);
                $brokenLinkCount += count($brokenLinks);

                $progress = ($entryCount > 0 ? ($currentEntry / $entryCount) : 0) / $this->totalBatches
                    + $this->batchIndex / $this->totalBatches;
                $this->setProgress($queue, $progress);
                $currentEntry++;
            }

            $transaction = Craft::$app->getDb()->beginTransaction();
            try {
                $freshScanRecord = ScanHistoryRecord::findOne($this->scanId);

                if ($this->forceFullScan && $this->batchIndex === $this->totalBatches - 1) {
                    $freshScanRecord->totalBrokenLinks = BrokenLinkRecord::find()->count();
                } else {
                    $freshScanRecord->totalBrokenLinks += $brokenLinkCount;
                }

                if ($this->batchIndex === $this->totalBatches - 1) {
                    $freshScanRecord->status = ScanHistoryRecord::STATUS_COMPLETED;
                    $freshScanRecord->endTime = new \DateTime();
                }

                $freshScanRecord->save();
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Craft::error('Error checking for broken links: ' . $e->getMessage(), __METHOD__);

            // Mark the scan failed regardless of which batch this is.
            $scanRecord->status = ScanHistoryRecord::STATUS_FAILED;
            $scanRecord->save();

            throw $e;
        }
    }

    private function checkPageLinks(Client $client, string $url, Entry $entry): array
    {
        $brokenLinks = [];

        try {
            $pageResponse = $client->get($url);
            $html = $pageResponse->getBody()->getContents();

            preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)".*?>(.*?)<\/a>/is', $html, $matches);
            $urls = $matches[1] ?? [];
            $linkTexts = $matches[2] ?? [];

            foreach ($urls as $index => $link) {
                $absoluteUrl = $this->resolveUrl($url, $link);
                $linkText = strip_tags(trim($linkTexts[$index] ?? ''));

                if (!preg_match('/^https?:\/\//', $absoluteUrl)) {
                    continue;
                }

                try {
                    $headResponse = $client->head($absoluteUrl);
                    $statusCode = $headResponse->getStatusCode();

                    if ($statusCode >= 400) {
                        $brokenLinks[] = $this->saveBrokenLink([
                            'url' => $absoluteUrl,
                            'status' => 'Broken',
                            'statusCode' => $statusCode,
                            'entryId' => $entry->id,
                            'entryTitle' => $entry->title ?? $entry->slug ?? 'N/A',
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
                        'entryTitle' => $entry->title ?? $entry->slug ?? 'N/A',
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

    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        return (string) \GuzzleHttp\Psr7\UriResolver::resolve(
            new \GuzzleHttp\Psr7\Uri($baseUrl),
            new \GuzzleHttp\Psr7\Uri($relativeUrl)
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
