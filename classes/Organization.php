<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class Organization extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'Organization';
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
        $organization = $schema['organization'] ?? [];

        if (empty($organization)) return [];

        $data = $this->data;

        $name = $organization['name'] ?? '';
        if ($name) $data['name'] = $name;

        $legalName = $organization['legal_name'] ?? '';
        if ($legalName) $data['legalName'] = $legalName;

        $taxId = $organization['tax_id'] ?? '';
        if ($taxId) $data['taxId'] = $taxId;

        $vatId = $organization['vat_id'] ?? '';
        if ($vatId) $data['vatId'] = $vatId;

        $description = $organization['description'] ?? '';
        if ($description) $data['description'] = $description;

        $telephone = $organization['phone'] ?? '';
        if ($telephone) $data['phone'] = $telephone;

        $logo = $organization['logo'] ?? '';
        if ($logo) $data['logo'] = $logo;

        $url = $organization['url'] ?? '';
        if ($url) $data['url'] = $url;

        $email = $organization['email'] ?? '';
        if ($email) $data['email'] = $email;

        $foundingDate = $organization['founding_date'] ?? '';
        if ($foundingDate) $data['foundingDate'] = $foundingDate;

        $streetAddress      = $organization['street_address']   ?? '';
        $addressLocality    = $organization['city']             ?? '';
        $addressRegion      = $organization['state']            ?? '';
        $postalCode         = $organization['zip_code']         ?? '';

        if ($streetAddress || $addressLocality || $addressRegion || $postalCode)
        {
            $address = ['@type' => 'PostalAddress'];

            if ($streetAddress)     $address['streetAddress']   = $streetAddress;
            if ($addressLocality)   $address['addressLocality'] = $addressLocality;
            if ($addressLocality)   $address['addressLocality'] = $addressLocality;
            if ($postalCode)        $address['postalCode']      = $postalCode;

            $data['address'] = $address;
        }

        $founders = $organization['founders'] ?? [];

        if (!empty($founders))
        {
            $data['founders'] = [];

            foreach ($founders as $founder)
            {
                if (array_key_exists('name', $founder) && $founder['name'])
                {
                    $data['founders'][] = [
                        '@type' => 'Person',
                        'name'  => $founder['name'],
                    ];
                }
            }
        }

        $similars = $organization['similars'] ?? [];

        if (!empty($similars))
        {
            $data['sameAs'] = [];

            foreach ($similars as $similar)
            {
                if (array_key_exists('same_as', $similar) && $similar['same_as'])
                {
                    $data['sameAs'][] = $similar['same_as'];
                }
            }
        }

        $areas = $organization['area_served'] ?? [];

        if (!empty($areas))
        {
            $data['areaServed'] = [];

            foreach ($areas as $areaServed)
            {
                if (array_key_exists('area', $areaServed) && $areaServed['area'])
                {
                    $data['areaServed'][] = $areaServed['area'];
                }
            }
        }

        $hours = $organization['opening_hours'] ?? [];

        if (!empty($hours))
        {
            $data['openingHours'] = [];

            foreach ($hours as $hour)
            {
                if (array_key_exists('entry', $hour) && $hour['entry'])
                {
                    $data['openingHours'][] = $hour['entry'];
                }
            }
        }

        $offers = $organization['offer_catalog'] ?? [];

        if (!empty($offers))
        {
            $data['hasOfferCatalog'] = [];

            foreach ($offers as $offer)
            {
                $offerName          = $offer['offer']       ?? '';
                $offerDescription   = $offer['description'] ?? '';
                $offerUrl           = $offer['url']         ?? '';
                $offerImage         = $offer['image']       ?? '';

                $offerCatalog = [
                    '@type'         => 'OfferCatalog',
                    'name'          => $offerName,
                    'description'   => $offerDescription,
                    'url'           => $offerUrl,
                    'image'         => $offerImage,
                ];

                if (array_key_exists('offered_item', $offer) && $offer['offered_item'])
                {
                    foreach ($offer['offered_item'] as $service)
                    {
                        $serviceName    = $service['name']  ?? '';
                        $serviceUrl     = $service['url']   ?? '';

                        if ($serviceName || $serviceUrl)
                        {
                            $itemOffered = [
                                '@type' => 'Offer',
                                'itemOffered'   => [
                                    '@type'     => 'Service',
                                ],
                            ];

                            if ($serviceName)   $itemOffered['itemOffered']['name'] = $serviceName;
                            if ($serviceUrl)    $itemOffered['itemOffered']['url']  = $serviceUrl;

                            $offerCatalog['itemListElement'] = $offer;
                        }
                    }
                }

                $data['hasOfferCatalog'][] = $offerCatalog;
            }
        }

        if (array_key_exists('organization_rating_enabled', $schema)
            && $schema['organization_rating_enabled'])
        {
            $ratingValue = $organization['rating_value'] ?? '';
            $reviewCount = $organization['review_count'] ?? '';

            if ($ratingValue || $reviewCount)
            {
                $orgaRating = ['@type' => 'AggregateRating'];

                if ($ratingValue) $orgaRating['ratingValue'] = $ratingValue;
                if ($reviewCount) $orgaRating['reviewCount'] = $reviewCount;

                $data['aggregateRating'] = $orgaRating;
            }
        }

        return $data;
    }
}