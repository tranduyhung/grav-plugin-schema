<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class MusicEvent extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'MusicEvent';
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

        if (empty($schema)) return $returnedData;

        $event = $schema['music_event'] ?? [];

        if (empty($event)) return [];

        $baseUrl = $this->grav['uri']->base();
        $data = $this->data;

        $artists = $event['performers'] ?? [];

        $eventName = $event['name'] ?? '';
        if ($eventName) $data['name'] = $eventName;

        $eventDescription = $event['description'] ?? '';
        if ($eventDescription) $data['description'] = $eventDescription;

        $eventUrl = $event['url'] ?? '';
        if ($eventUrl) $data['url'] = $eventUrl;

        $locationName       = $event['location_name']       ?? '';
        $locationAddress    = $event['location_address']    ?? '';

        if ($locationName || $locationAddress)
        {
            $location = ['@type' => 'MusicVenue'];

            if ($locationName)      $location['name']       = $locationName;
            if ($locationAddress)   $location['address']    = $locationAddress;

            $data['location'] = $location;
        }

        $startDate  = $event['start_date']   ?? '';
        $endDate    = $event['end_date']     ?? '';

        if ($startDate) $data['startDate']  = date('c', strtotime($startDate));
        if ($endDate)   $data['endDate']    = date('c', strtotime($endDate));

        if (!empty($artists))
        {
            $data['performer'] = [];

            foreach ($artists as $artist)
            {
                $performerType  = $artist['performer_type'] ?? '';
                $name           = $artist['name']           ?? '';
                $sameAs         = $artist['sameAs']         ?? '';

                if ($performerType && $name)
                {
                    $performer = [
                        '@type' => $performerType,
                        'name'  => $name,
                    ];

                    if ($sameAs) $performer['sameAs'] = $sameAs;

                    $data['performer'][] = $performer;
                }
            }
        }

        $works = $event['works_performed'] ?? [];

        if (!empty($works))
        {
            $data['workPerformed'] = [];

            foreach ($works as $w)
            {
                $name   = $work['name']     ?? '';
                $sameAs = $work['sameAs']   ?? '';

                if (!$name) continue;

                $work = ['name' => $name];

                if ($sameAs) $work['sameAs'] = $sameAs;

                $data['workPerformed'][] = $work;
            }
        }

        $imageUrl = $event['image'] ?? '';

        if ($imageUrl)
        {
            $imageData = $this->getImageData($imageUrl);
            $data['image'] = [
                '@type'     => 'ImageObject',
                'width'     => $imageData['width'],
                'height'    => $imageData['height'],
                'url'       => $baseUrl .  $imageData['url'],
            ];
        }

        $offerPrice         = $event['offers_price']            ?? '';
        $offerPriceCurrency = $event['offers_price_currency']   ?? '';
        $offerUrl           = $event['offers_url']              ?? '';

        if ($offerPrice || $offerPriceCurrency || $offerUrl)
        {
            $data['offers'] = ['@type'=> 'Offer'];

            if ($offerPrice)            $data['offers']['price']            = $offerPrice;
            if ($offerPriceCurrency)    $data['offers']['priceCurrency']    = $offerPriceCurrency;
            if ($offerUrl)              $data['offers']['url']              = $offerUrl;
        }

        return $data;
    }
}