<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class Event extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'Event';
        parent::__construct();
    }

    /**
     * Generate structured data. Return an array of schema items.
     * 
     * @return array
     */
    public function generateStructuredData(): array
    {
        $returnedData = [];
        $page = $this->page;
        $header = $page->header();
        $schema = $header->schema ?? [];
        $event = $schema['event'] ?? [];

        if (empty($event)) return [];

        $data = $this->data;

        $eventName = $event['name'] ?? '';
        if ($eventName) $data['name'] = $eventName;

        $eventDescription = $event['description'] ?? '';
        if ($eventDescription) $data['description'] = $eventDescription;

        $offerPrice         = $event['offers_price']      ?? '';
        $offerPriceCurrency = $event['offers_currency']   ?? '';
        $offerUrl           = $event['offers_url']        ?? '';

        if ($offerPrice || $offerPriceCurrency || $offerUrl)
        {
            $offer = ['@type' => 'Offer'];

            if ($offerPrice)            $offer['price']         = $offerPrice;
            if ($offerPriceCurrency)    $offer['priceCurrency'] = $offerPriceCurrency;
            if ($offerUrl)              $offer['url']           = $offerUrl;

            $data['offers'] = $offer;
        }

        $startDate  = $event['start_date'] ?? '';
        $endDate    = $event['end_date']   ?? '';

        if ($startDate) $data['startDate']  = date('c', strtotime($startDate));
        if ($endDate)   $data['endDate']    = date('c', strtotime($endDate));

        $locationName       = $event['location_name']                     ?? '';
        $addressLocality    = $event['location_address_address_locality'] ?? '';
        $addressRegion      = $event['location_address_address_region']   ?? '';
        $streetAddress      = $event['event_location_streetAddress']            ?? '';
        $locationUrl        = $event['location_url']                ?? '';

        if ($locationName || $addressLocality || $addressRegion || $streetAddress || $locationUrl)
        {
            $location = ['@type'     => 'Place'];

            if ($locationName) $location['name'] = $locationName;

            $address = ['@type' => 'PostalAddress'];

            if ($addressLocality)  $address['addressLocality'] = $addressLocality;
            if ($addressRegion)    $address['addressRegion']   = $addressRegion;
            if ($streetAddress)    $address['streetAddress']   = $streetAddress;

            $location['address'] = $address;

            if ($locationUrl) $location['url'] = $locationUrl;

            $data['location'] = $location;
        }

        return $data;
    }
}