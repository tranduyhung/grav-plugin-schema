<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class Restaurant extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'Restaurant';
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
        $restaurant = $schema['restaurant'] ?? [];

        if (empty($restaurant)) return [];

        $data = $this->data;

        $name = $restaurant['name'] ?? '';
        if ($name) $data['name'] = $name;

        $imageUrl = $restaurant['image'] ?? '';

        if ($imageUrl)
        {
            $imageData = $this->getImageData($imageUrl);
            $data['image'] = [
                '@type'     => 'ImageObject',
                'width'     => $imageData['width'],
                'height'    => $imageData['height'],
                'url'       => $this->grav['uri']->base() .  $imageData['url'],
            ];
        }

        $addressLocality    = $restaurant['address_address_locality']   ?? '';
        $addressRegion      = $restaurant['address_address_region']     ?? '';
        $streetAddress      = $restaurant['address_street_address']     ?? '';
        $postalCode         = $restaurant['address_postal_code']        ?? '';

        if ($addressLocality || $addressRegion || $streetAddress || $postalCode)
        {
            $address = ['@type' => 'PostalAddress'];

            if ($addressLocality)   $address['addressLocality'] = $addressLocality;
            if ($addressRegion)     $address['addressRegion']   = $addressRegion;
            if ($streetAddress)     $address['streetAddress']   = $streetAddress;
            if ($postalCode)        $address['postalCode']      = $postalCode;

            $data['address'] = $address;
        }

        $areas = $restaurant['area_served'] ?? [];

        if (!empty($areas))
        {
            $organization['areaServed'] = [];

            foreach ($areas as $areaServed)
            {
                if (array_key_exists('area', $areaServed) && $areaServed['area'])
                {
                    $data['areaServed'][] = $areaServed['area'];
                }
            }
        }

        $servesCuisine = $restaurant['serves_cuisine'] ?? '';
        if ($servesCuisine) $data['servesCuisine'] = $servesCuisine;

        $priceRange = $restaurant['price_range'] ?? '';
        if ($priceRange) $data['priceRange'] = $priceRange;

        $telephone = $restaurant['telephone'] ?? '';
        if ($telephone) $data['telephone'] = $telephone;

        return $data;
    }
}