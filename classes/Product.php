<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class Product extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'Product';
        parent::__construct();
    }

    /**
     * Generate structured data. Return an array of schema items.
     * 
     * @return array
     */
    public function generateStructuredData(): array
    {
        $page = $this->page;
        $header = $page->header();
        $schema = $header->schema ?? [];
        $product = $schema['product'] ?? [];

        if (empty($product)) return [];

        $baseUrl = $this->grav['uri']->base();
        $data = $this->data;

        $name = $product['name'] ?? '';
        if ($name) $data['name'] = $name;

        $category = $product['category'] ?? '';
        if ($category) $data['category'] = $category;

        $description = $product['description'] ?? '';
        if ($description) $data['description'] = $description;

        $brand = $product['brand'] ?? '';

        if ($brand)
        {
            $data['brand'] = [
                '@type' => 'Brand',
                'name'  => $brand,
            ];
        }

        $productImages = $product['image'] ?? [];

        if (!empty($productImages))
        {
            $data['image'] = []; 

            foreach ($productImages as $key => $value)
            {
                $imageArray = $productImages[$key] ?? [];

                if (!empty($imageArray))
                {
                    foreach($imageArray as $newKey => $newValue)
                    {
                        $imageData = $this->getImageData($imageArray[$newKey]);
                        $data['image'][] = $baseUrl .  $imageData['url'];
                    }
                }
            }
        }

        $offers = $product['offers'] ?? [];

        if (!empty($offers))
        {
            $data['offers'] = [];

            foreach ($offers as $key => $value)
            {
                $priceCurrency  = $offers[$key]['offer_price_currency'] ?? '';
                $price          = $offers[$key]['offer_price']          ?? '';
                $validFrom      = $offers[$key]['offer_valid_from']     ?? '';
                $validUntil     = $offers[$key]['offer_valid_until']    ?? '';
                $availability   = $offers[$key]['offer_availability']   ?? '';

                $data['offers'][$key] = [
                    '@type'             => 'Offer',
                    'priceCurrency'     => $priceCurrency,
                    'price'             => $price,
                    'validFrom'         => $validFrom,
                    'priceValidUntil'   => $validUntil,
                    'availability'      => $availability,
                ];
            }
        }

        $aggregateRating = $product['aggregate_rating'] ?? false;

        if ($aggregateRating)
        {
            $ratingValue    = $product['rating_value']    ?? '';
            $ratingCount    = $product['rating_count']    ?? '';
            $worstRating    = $product['worst_rating']    ?? '';
            $bestRating     = $product['best_rating']     ?? '';

            $data['aggregateRating'] = [
                '@type'         => 'AggregateRating',
                'ratingValue'   => $ratingValue,
                'ratingCount'   => $ratingCount,
                'worstRating'   => $worstRating,
                'bestRating'    => $bestRating,
            ];
        }

        $reviews = $product['reviews'] ?? [];

        if (!empty($reviews))
        {
            $data['review'] = [];

            foreach ($reviews as $r)
            {
                $authorName             = $r['author_name']             ?? '';
                $authorJobTitle         = $r['author_job_title']        ?? '';
                $authorInternalImage    = $r['author_internal_image']   ?? '';
                $authorExternalImage    = $r['author_external_image']   ?? '';
                $authorUrl              = $r['author_url']              ?? '';

                $author = [
                    '@type'     => 'Person',
                    'name'      => $authorName,
                ];

                if ($authorJobTitle)
                {
                    $author['jobTitle'] = $authorJobTitle;
                }

                if ($authorUrl)
                {
                    $author['url'] = $authorUrl;
                }

                if ($authorInternalImage)
                {
                    $imageData = $this->getImageData($authorInternalImage);
                    $author['image'] = $baseUrl .  $imageData['url'];
                }
                elseif ($authorExternalImage)
                {
                    $author['image'] = $authorExternalImage;
                }

                $datePublished  = $r['date_published']  ?? '';
                $name           = $r['name']            ?? '';
                $reviewBody     = $r['review_body']     ?? '';
                $rating         = $r['review_rating']   ?? false;

                $review = [
                    '@type'         => 'Review',
                    'author'        => $author,
                    'datePublished' => $datePublished,
                    'name'          => $name,
                    'reviewBody'    => $reviewBody,
                ];

                if ($rating)
                {
                    $bestRating     = $r['best_rating']     ?? '';
                    $ratingValue    = $r['rating_value']    ?? '';
                    $worstRating    = $r['worst_rating']    ?? '';

                    $review['reviewRating'] = [
                        '@type'         => 'Rating',
                        'bestRating'    => $bestRating,
                        'ratingValue'   => $ratingValue,
                        'worstRating'   => $worstRating,
                    ];
                }

                $data['review'][] = $review;
            }
        }

        return $data;
    }
}