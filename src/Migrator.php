<?php

use Illuminate\Support\Collection;

class Migrator
{
    /**
     * @var \Illuminate\Database\Connection
     */
    protected $db;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $dryRun;

    public function __construct(\Illuminate\Database\Connection $db, \Psr\Log\LoggerInterface $logger, bool $dryRun)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->dryRun = $dryRun;
    }

    public function __invoke(array $skuMapping)
    {
        $this->logger->info('Start mapping', ['dryRun' => $this->dryRun]);

        $skuMapping = $this->enrichSKUMappingWithNewArticlesIds($skuMapping);

        // skus can be numbers, what collides with the following where in clause
        $oldSKUs = $skuMapping->keys()->map(function ($sku) { return (string)$sku; });

        $this->db->beginTransaction();

        $sOrderDetailsQuery = $this->db->table('s_order_details')
            ->whereIn('articleordernumber', $oldSKUs)
            ->select(['id', 'articleordernumber', 'articleID']);

        $sOrderDetailsQuery->get()->each(function ($sOrderDetails) use ($skuMapping) {
            $newData = $skuMapping->get(strtolower($sOrderDetails->articleordernumber));

            if (!$newData) {
                $this->logger->warning('Failed to find matching new data, skipping', [
                    'sOrderDetailsId' => $sOrderDetails->id, 'oldSKU' => $sOrderDetails->articleordernumber
                ]);
                return;
            }

            $this->logger->info('Updating s_order_details', [
                'sOrderDetailsId' => $sOrderDetails->id,
                'oldSKU' => $sOrderDetails->articleordernumber, 'newSKU' => $newData['sku'],
                'oldArticleId' => $sOrderDetails->articleID, 'newArticleId' => $newData['sArticlesId']
            ]);

            $this->db->table('s_order_details')
                ->where('id', $sOrderDetails->id)
                ->update(['articleordernumber' => $newData['sku'], 'articleID' => $newData['sArticlesId']]);
        });

        $this->dryRun ? $this->db->rollBack(): $this->db->commit();
    }

    protected function enrichSKUMappingWithNewArticlesIds(array $skuMapping): Collection
    {
        return collect($skuMapping)
            ->map(function (string $sku) {
                $sArticlesId = $this->getArticlesIdForSKU($sku);

                return ['sku' => $sku, 'sArticlesId' => $sArticlesId];
            })
            ->mapWithKeys(function ($newData, $oldSKU) {
                return [strtolower($oldSKU) => $newData];
            });;
    }

    protected function getArticlesIdForSKU(string $sku)
    {
        $sArticles = $this->db->table('s_articles')
            ->join('s_articles_details', 's_articles_details.articleID', '=', 's_articles.id')
            ->where('s_articles_details.ordernumber', '=', $sku)
            ->get(['s_articles.id']);

        if ($sArticles->count() > 1) {
            $this->logger->warning(
                'Found more than one s_articles.id per for a SKU',
                ['sku' => $sku, 'sArticlesIds' => $sArticles->pluck('id')]
            );
        }

        $sArticle = $sArticles->first();
        if (!$sArticle) {
            $this->logger->warning('Failed to find a new s_articles.id for a SKU', ['sku' => $sku]);
        }

        return $sArticle ? $sArticle->id : null;
    }
}