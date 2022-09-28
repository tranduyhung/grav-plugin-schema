<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Data\Blueprints;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Helpers\Exif;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Iterator;

class SchemaPlugin extends Plugin
{
    /**
     * Get subscribed events.
     * 
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onPageInitialized'    => ['onPageInitialized', 0],
        ];
    }

    public function array_filter_recursive(array $array, callable $callback = null )
    {
        $array = is_callable($callback )? array_filter($array, $callback) : array_filter($array);

        foreach ($array as &$value)
        {
            if (is_array($value))
            {
                $value = $this->array_filter_recursive($value);
            }
        }

        return $array;
    }

    private function seoGetimage($imageUrl)
    {
        $imageData = [];
        $pattern = '~((\/[^\/]+)+)\/([^\/]+)~';
        $replacement = '$1';
        $fixedUrl = preg_replace($pattern, $replacement, $imageUrl);
        $imageName = preg_replace($pattern, '$3', $imageUrl);
        $imageArray = $this->grav['page']->find($fixedUrl)->media()->images();
        $keyImages = array_keys($imageArray);
        $imageKey = array_search($imageName, $keyImages);
        $keyValue = $keyImages[$imageKey];
        //$imageKey = array_shift($imageArray);
        $imageObject = $imageArray[$keyValue];

        $im = getimagesize($imageObject->path());
        $imageData = [
            'width'     => "$im[0]",
            'height'    => "$im[1]",
            'url'       => $imageObject->url(),
        ];

        return $imageData;
    }

    private function cleanMarkdown($text)
    {
        $text = strip_tags($text);
        $rules = [
            '/{%[\s\S]*?%}[\s\S]*?/'                 => '',    // remove twig include
            '/<style(?:.|\n|\r)*?<\/style>/'         => '',    // remove style tags
            '/<script[\s\S]*?>[\s\S]*?<\/script>/'   => '',  // remove script tags
            '/(#+)(.*)/'                             => '\2',  // headers
            '/(&lt;|<)!--\n((.*|\n)*)\n--(&gt;|\>)/' => '',    // comments
            '/(\*|-|_){3}/'                          => '',    // hr
            '/!\[([^\[]+)\]\(([^\)]+)\)/'            => '',    // images
            '/\[([^\[]+)\]\(([^\)]+)\)/'             => '\1',  // links
            '/(\*\*|__)(.*?)\1/'                     => '\2',  // bold
            '/(\*|_)(.*?)\1/'                        => '\2',  // emphasis
            '/\~\~(.*?)\~\~/'                        => '\1',  // del
            '/\:\"(.*?)\"\:/'                        => '\1',  // quote
            '/```(.*)\n((.*|\n)+)\n```/'             => '\2',  // fence code
            '/`(.*?)`/'                              => '\1',  // inline code
            '/(\*|\+|-)(.*)/'                        => '\2',  // ul lists
            '/\n[0-9]+\.(.*)/'                       => '\2',  // ol lists
            '/(&gt;|\>)+(.*)/'                       => '\2',  // blockquotes
        ];

        $text = str_replace(".\n", '.', $text);
        $text = str_replace("\n", '. ', $text);
        $text = str_replace('"', '', $text);
        $text = str_replace('<p', '', $text);
        $text = str_replace('</p>', '', $text);

        foreach ($rules as $regex => $rep)
        {
            if (is_callable($rep))
            {
               $text = preg_replace_callback($regex, $rep, $text);
            }
            else
            {
                $text = preg_replace($regex, $rep, $text);
            }
        }

        return substr($text, 0, 320);
        // htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Listen to event hooks.
     * 
     * @return void
     */
    public function onPluginsInitialized()
    {
        if (!$this->isAdmin())
        {
            $events = [
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            ];
        }
        else
        {
            $this->active = false;

            $events = [
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
                'onBlueprintCreated' => ['onBlueprintCreated', 0],
            ];
        }

        $this->enable($events);
    }

    /**
     * [onPageInitialized:0]
     *
     * @return void
     */
    public function onPageInitialized()
    {
        $page = $this->grav['page'];
        $header = $page->header();

        if (!property_exists($header, 'schema'))
        {
            return;
        }

        $config = $this->mergeConfig($page);
        $content = strip_tags($page->content());
        $assets = $this->grav['assets'];
        $pattern = '~((\/[^\/]+)+)\/([^\/]+)~';
        $replacement = '$1';
        $outputJson = "";
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.admin.route');
        $microdata = [];
        $schema = $header->schema;

        if (array_key_exists('music_event_enabled', $schema)
            && $schema['music_event_enabled']
            && $this->config['plugins']['schema']['music_event_type'])
        {
            $musicEventsArray = $schema['music_events'] ?? [];

            if (count($musicEventsArray) > 0)
            {
                foreach ($musicEventsArray as $event)
                {
                    $artists = $event['music_event_performers'] ?? [];

                    $musicEvent = [
                        '@context'  => 'http://schema.org',
                        '@type'     => 'MusicEvent',
                    ];

                    $eventName = $event['music_event_name'] ?? '';
                    if ($eventName) $musicEvent['name'] = $eventName;

                    $eventDescription = $event['music_event_description'] ?? '';
                    if ($eventDescription) $musicEvent['description'] = $eventDescription;

                    $eventUrl = $event['music_event_url'] ?? '';
                    if ($eventUrl) $musicEvent['url'] = $eventUrl;

                    $locationName       = $event['music_event_location_name']       ?? '';
                    $locationAddress    = $event['music_event_location_address']    ?? '';

                    if ($locationName || $locationAddress)
                    {
                        $location = ['@type' => 'MusicVenue'];

                        if ($locationName)      $location['name']       = $locationName;
                        if ($locationAddress)   $location['address']    = $locationAddress;

                        $musicEvent['location'] = $location;
                    }

                    $eventStartDate = $event['music_event_start_date']  ?? '';
                    $eventEndDate   = $event['music_event_end_date']    ?? '';

                    if ($eventStartDate)    $musicEvent['startDate']    = date('c', strtotime($eventStartDate));
                    if ($eventEndDate)      $musicEvent['endDate']      = date('c', strtotime($eventEndDate));

                    if (count($artists) > 0)
                    {
                        $musicEvent['performer'] = [];

                        foreach ($artists as $artist)
                        {
                            $performerType  = $artist['performer_type'] ?? '';
                            $name           = $artist['name']           ?? '';
                            $sameAs         = $artist['sameAs']         ?? '';

                            if ($performerType && $name)
                            {
                                $performer = [
                                    '@type'     => $performerType,
                                    'name'      => $name,
                                ];

                                if ($sameAs) $performer['sameAs'] = $sameAs;

                                $musicEvent['performer'][] = $performer;
                            }
                        }
                    }

                    $works = $event['music_event_work_performed'] ?? [];

                    if (count($works) > 0)
                    {
                        $musicEvent['workPerformed'] = [];

                        foreach ($works as $w)
                        {
                            $name   = $work['name']     ?? '';
                            $sameAs = $work['sameAs']   ?? '';

                            if ($name)
                            {
                                $work = ['name' => $name];

                                if ($sameAs) $work['sameAs'] = $sameAs;

                                $musicEvent['workPerformed'][] = $work;
                            }
                        }
                    }

                    $imageUrl = $event['music_event_image'] ?? '';

                    if ($imageUrl)
                    {
                        $imageData = $this->seoGetimage($imageUrl);
                        $musicEvent['image'] = [
                            '@type'     => 'ImageObject',
                            'width'     => $imageData['width'],
                            'height'    => $imageData['height'],
                            'url'       => $this->grav['uri']->base() .  $imageData['url'],
                        ];
                    }

                    $offerPrice         = $event['music_event_offers_price']            ?? '';
                    $offerPriceCurrency = $event['music_event_offers_price_currency']   ?? '';
                    $offerUrl           = $event['music_event_offers_url']              ?? '';

                    if ($offerPrice || $offerPriceCurrency || $offerUrl)
                    {
                        $musicEvent['offers'] = ['@type'=> 'Offer'];

                        if ($offerPrice)            $musicEvent['offers']['price']          = $offerPrice;
                        if ($offerPriceCurrency)    $musicEvent['offers']['priceCurrency']  = $offerPriceCurrency;
                        if ($offerUrl)              $musicEvent['offers']['url']            = $offerUrl;
                    }

                    $microdata[] = $musicEvent;
                }
            }
        }

        if (array_key_exists('event_enabled', $schema)
            && $schema['event_enabled']
            && $this->config['plugins']['schema']['event_type'])
        {
            $events = $schema['events'] ?? [];

            if (count($events) > 0)
            {
                foreach ($events as $e)
                {
                    $event = [
                        '@context'  => 'http://schema.org',
                        '@type'     => 'Event',
                    ];

                    $eventName = $e['event_name'] ?? '';
                    if ($eventName) $event['name'] = $eventName;

                    $eventDescription = $e['event_description'] ?? '';
                    if ($eventDescription) $event['description'] = $eventDescription;

                    $offerPrice         = $e['event_offers_price']      ?? '';
                    $offerPriceCurrency = $e['event_offers_currency']   ?? '';
                    $offerUrl           = $e['event_offers_url']        ?? '';

                    if ($offerPrice || $offerPriceCurrency || $offerUrl)
                    {
                        $offer = ['@type' => 'Offer'];

                        if ($offerPrice)            $offer['price']         = $offerPrice;
                        if ($offerPriceCurrency)    $offer['priceCurrency'] = $offerPriceCurrency;
                        if ($offerUrl)              $offer['url']           = $offerUrl;

                        $event['offers'] = $offer;
                    }

                    $eventStartDate = $e['event_start_date']  ?? '';
                    $eventEndDate   = $e['event_end_date']    ?? '';

                    if ($eventStartDate)    $event['startDate']    = date('c', strtotime($eventStartDate));
                    if ($eventEndDate)      $event['endDate']      = date('c', strtotime($eventEndDate));

                    $eventLocationName = $e['event_location_name'] ?? '';

                    if ($eventLocationName)
                    {
                        $location = [
                            '@type'     => 'Place',
                            'name'      => $eventLocationName,
                        ];

                        $eventAddressLocality   = $e['event_location_address_address_locality'] ?? '';
                        $eventAddressRegion     = $e['event_location_address_address_region']   ?? '';
                        $eventStreetAddress     = $e['event_location_streetAddress']            ?? '';

                        if ($eventAddressLocality || $eventAddressRegion || $eventStreetAddress)
                        {
                            $address = ['@type' => 'PostalAddress'];

                            if ($eventAddressLocality)  $address['addressLocality'] = $eventAddressLocality;
                            if ($eventAddressRegion)    $address['addressRegion']   = $eventAddressRegion;
                            if ($eventStreetAddress)    $address['streetAddress']   = $eventStreetAddress;

                            $location['address'] = $address;
                        }

                        $eventLocationUrl = $e['music_event_location_url'] ?? '';
                        if ($eventLocationUrl) $location['url'] = $eventLocationUrl;

                        $event['location'] = $location;
                    }


                    $microdata[] = $event;
                }
            }
        }

        if (array_key_exists('person_enabled', $schema)
            && $schema['person_enabled']
            && $this->config['plugins']['schema']['person_type'])
        {
            $persons = $schema['persons'] ?? [];

            if (count($persons) > 0)
            {
                foreach ($persons as $p)
                {
                    $name = $p['person_name'] ?? '';

                    if ($name)
                    {
                        $person = [
                            '@context'  => 'http://schema.org',
                            '@type'     => 'Person',
                            'name'      => $name,
                        ];

                        $jobTitle = $p['person_jobTitle'] ?? '';
                        if ($jobTitle) $person['jobTitle'] = $jobTitle;

                        $addressLocality    = $p['person_address_address_locality'] ?? '';
                        $addressRegion      = $p['person_address_address_region']   ?? '';

                        if ($addressLocality || $addressRegion)
                        {
                            $address = ['@type' => 'PostalAddress'];

                            if ($addressLocality)   $address['addressLocality'] = $addressLocality;
                            if ($addressRegion)     $address['addressRegion']   = $addressRegion;

                            $person['address'] = $address;
                        }

                        $microdata[] = $person;
                    }
                }
            }
        }

        if (array_key_exists('organization_enabled', $schema)
            && $schema['organization_enabled']
            && $this->config['plugins']['schema']['organization_type']
            && array_key_exists('organization', $schema))
        {
            $organization = [
                '@context'      => 'http://schema.org',
                '@type'         => 'Organization',
            ];

            $o = $schema['organization'];

            $name = $o['name'] ?? '';
            if ($name) $organization['name'] = $name;

            $legalName = $o['legal_name'] ?? '';
            if ($legalName) $organization['legalName'] = $legalName;

            $taxId = $o['tax_id'] ?? '';
            if ($taxId) $organization['taxId'] = $taxId;

            $vatId = $o['vat_id'] ?? '';
            if ($vatId) $organization['vatId'] = $vatId;

            $description = $o['description'] ?? '';
            if ($description) $organization['description'] = $description;

            $telephone = $o['phone'] ?? '';
            if ($telephone) $organization['phone'] = $telephone;

            $logo = $o['logo'] ?? '';
            if ($logo) $organization['logo'] = $logo;

            $url = $o['url'] ?? '';
            if ($url) $organization['url'] = $url;

            $email = $o['email'] ?? '';
            if ($email) $organization['email'] = $email;

            $foundingDate = $o['founding_date'] ?? '';
            if ($foundingDate) $organization['foundingDate'] = $foundingDate;

            $streetAddress      = $o['street_address'] ?? '';
            $addressLocality    = $o['city']           ?? '';
            $addressRegion      = $o['state']          ?? '';
            $postalCode         = $o['zip_code']       ?? '';

            if ($streetAddress || $addressLocality || $addressRegion || $postalCode)
            {
                $address = ['@type' => 'PostalAddress'];

                if ($streetAddress)     $address['streetAddress']   = $streetAddress;
                if ($addressLocality)   $address['addressLocality'] = $addressLocality;
                if ($addressLocality)   $address['addressLocality'] = $addressLocality;
                if ($postalCode)        $address['postalCode']      = $postalCode;

                $organization['address'] = $address;
            }

            $founders = $o['founders'] ?? [];

            if (count($founders) > 0)
            {
                $organization['founders'] = [];

                foreach ($founders as $founder)
                {
                    if (array_key_exists('name', $founder)
                        && $founder['name'])
                    {
                        $organization['founders'][] = [
                            '@type' => 'Person',
                            'name'  => $founder['name'],
                        ];
                    }
                }
            }

            $similars = $o['similars'] ?? [];

            if (count($similars) > 0)
            {
                $organization['sameAs'] = [];

                foreach ($similars as $similar)
                {
                    if (array_key_exists('same_as', $similar)) $organization['sameAs'][] = $similar['same_as'];
                }
            }

            $areas = $o['area_served'] ?? [];

            if (count($areas) > 0)
            {
                $organization['areaServed'] = [];

                foreach ($areas as $areaServed)
                {
                    if (array_key_exists('area', $areaServed)) $organization['areaServed'][] = $areaServed['area'];
                }
            }

            $hours = $o['opening_hours'] ?? [];

            if (count($hours) > 0)
            {
                $organization['openingHours'] = [];

                foreach ($hours as $hour)
                {
                    if (array_key_exists('entry', $hour)) $organization['openingHours'][] = $hour['entry'];
                }
            }

            $offers = $o['offer_catalog'] ?? [];

            if (count($offers) > 0)
            {
                $organization['hasOfferCatalog'] = [];

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

                    if (array_key_exists('offered_item', $offer)
                        && $offer['offered_item'])
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

                    $organization['hasOfferCatalog'][] = $offerCatalog;
                }
            }

            if (array_key_exists('organization_rating_enabled', $schema)
                && $schema['organization_rating_enabled'])
            {
                $ratingValue = $o['rating_value'] ?? '';
                $reviewCount = $o['review_count'] ?? '';

                if ($ratingValue || $reviewCount)
                {
                    $orgaRating = ['@type' => 'AggregateRating'];

                    if ($ratingValue) $orgaRating['ratingValue'] = $ratingValue;
                    if ($reviewCount) $orgaRating['reviewCount'] = $reviewCount;

                    $organization['aggregateRating'] = $orgaRating;
                }
            }

            $microdata[] = $organization;
        }

        if (array_key_exists('restaurant_enabled', $schema)
            && $schema['restaurant_enabled']
            && $this->config['plugins']['schema']['restaurant_type']
            && array_key_exists('restaurant', $schema))
        {
            $restaurant = [
                '@context'  => 'http://schema.org',
                '@type'     => 'Restaurant',
            ];

            $r = $schema['restaurant'];

            $name = $r['name'] ?? '';
            if ($name) $restaurant['name'] = $name;

            $imageUrl = $r['image'] ?? '';

            if ($imageUrl)
            {
                $imageData = $this->seoGetimage($imageUrl);
                $restaurant['image'] = [
                    '@type'     => 'ImageObject',
                    'width'     => $imageData['width'],
                    'height'    => $imageData['height'],
                    'url'       => $this->grav['uri']->base() .  $imageData['url'],
                ];
            }

            $addressLocality    = $r['address_address_locality']    ?? '';
            $addressRegion      = $r['address_address_region']      ?? '';
            $streetAddress      = $r['address_street_address']      ?? '';
            $postalCode         = $r['address_postal_code']         ?? '';

            if ($addressLocality || $addressRegion || $streetAddress || $postalCode)
            {
                $address = ['@type' => 'PostalAddress'];

                if ($addressLocality)   $address['addressLocality'] = $addressLocality;
                if ($addressRegion)     $address['addressRegion']   = $addressRegion;
                if ($streetAddress)     $address['streetAddress']   = $streetAddress;
                if ($postalCode)        $address['postalCode']      = $postalCode;

                $restaurant['address'] = $address;
            }

            $areas = $r['area_served'] ?? [];

            if (count($areas) > 0)
            {
                $organization['areaServed'] = [];

                foreach ($areas as $areaServed)
                {
                    if (array_key_exists('area', $areaServed)) $restaurant['areaServed'][] = $areaServed['area'];
                }
            }

            $servesCuisine = $r['serves_cuisine'] ?? '';
            if ($servesCuisine) $restaurant['servesCuisine'] = $servesCuisine;

            $priceRange = $r['price_range'] ?? '';
            if ($priceRange) $restaurant['priceRange'] = $priceRange;

            $telephone = $r['telephone'] ?? '';
            if ($telephone) $restaurant['telephone'] = $telephone;

            $microdata[] = $restaurant;
        }

        if (array_key_exists('product_enabled', $schema)
            && $schema['product_enabled']
            && $this->config['plugins']['schema']['product_type']
            && array_key_exists('product', $schema))
        {
            $product = [
                '@context'  => 'http://schema.org',
                '@type'     => 'Product',
            ];

            $name = $p['name'] ?? '';
            if ($name) $product['name'] = $name;

            $category = $p['category'] ?? '';
            if ($category) $product['category'] = $category;

            $description = $p['description'] ?? '';
            if ($description) $product['description'] = $description;

            $brand = $p['brand'] ?? '';

            if ($brand)
            {
                $product['brand'] = [
                    '@type' => 'Thing',
                    'name'  => $brand,
                ];
            }

            $p = $schema['product'];

            $productImages = $p['image'] ?? [];

            if (count($productImages) > 0)
            {
                $product['image'] = []; 

                foreach ($productImages as $key => $value)
                {
                    $imageArray = $productImages[$key] ?? [];

                    if (count($imageArray) > 0)
                    {
                        foreach($imageArray as $newKey => $newValue)
                        {
                            $imageData = $this->seoGetimage($imageArray[$newKey]);
                            $product['image'][] = $this->grav['uri']->base() .  $imageData['url'];
                        }
                    }
                }
            }

            $offers = $p['offers'] ?? [];

            if (count($offers) > 0)
            {
                $product['offers'] = [];

                foreach ($offers as $key => $value)
                {
                    $priceCurrency  = $offers[$key]['offer_price_currency'] ?? '';
                    $price          = $offers[$key]['offer_price']          ?? '';
                    $validFrom      = $offers[$key]['offer_valid_from']     ?? '';
                    $validUntil     = $offers[$key]['offer_valid_until']    ?? '';
                    $availability   = $offers[$key]['offer_availability']   ?? '';

                    $product['offers'][$key] = [
                        '@type'             => 'Offer',
                        'priceCurrency'     => $priceCurrency,
                        'price'             => $price,
                        'validFrom'         => $validFrom,
                        'priceValidUntil'   => $validUntil,
                        'availability'      => $availability,
                    ];
                }
            }

            $aggregateRating = $p['aggregate_rating'] ?? false;

            if ($aggregateRating)
            {
                $ratingValue    = $p['rating_value']    ?? '';
                $ratingCount    = $p['rating_count']    ?? '';
                $worstRating    = $p['worst_rating']    ?? '';
                $bestRating     = $p['best_rating']     ?? '';

                $product['aggregateRating'] = [
                    '@type'         => 'AggregateRating',
                    'ratingValue'   => $ratingValue,
                    'ratingCount'   => $ratingCount,
                    'worstRating'   => $worstRating,
                    'bestRating'    => $bestRating,
                ];
            }

            $reviews = $p['reviews'] ?? [];

            if (count($reviews) > 0)
            {
                $product['review'] = [];

                foreach ($reviews as $r)
                {
                    $author         = $r['author']          ?? '';
                    $datePublished  = $r['date_published']  ?? '';
                    $name           = $r['name']            ?? '';
                    $reviewBody     = $r['review_body']     ?? '';
                    $rating         = $r['review_rating']   ?? false;

                    $review = [
                        '@type'         => 'Review',
                        'author'        => [
                            '@type' => 'Person',
                            'name'  => $author,
                        ],
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

                    $product['review'][] = $review;
                }
            }

            $microdata[] = $product;
        }

        if (array_key_exists('article_enabled', $schema)
            && $schema['article_enabled']
            && $this->config['plugins']['schema']['article_type'])
        {
            $microdata['article'] = [
                '@context'          => 'http://schema.org',
                '@type'             => 'Article',
                'mainEntityOfPage'  => [
                    "@type" => "WebPage",
                    'url'   => $this->grav['uri']->base(),
                ],
                'articleBody'   =>  @$this->cleanMarkdown($content),
                'datePublished' => date('c', $page->date()),
                'dateModified'  => date('c', $page->modified()),
            ];

            $article = $schema['article'] ?? null;

            if (is_array($article))
            {
                if (isset($article['headline']) && $article['headline'])
                {
                    $microdata['article']['headline'] =  $article['headline'];
                }
                else
                {
                    $microdata['article']['headline'] = $page->title();
                }

                if (isset($article['description']) && $article['description'])
                {
                    $microdata['article']['description'] = $article['description'];
                }
                else
                {
                    $microdata['article']['description'] = substr($content, 0, 140);
                }

                if (isset($article['author']) && $article['author'])
                {
                    $microdata['article']['author'] = $article['author'];
                }

                $publisherName      = $article['publisher_name']        ?? '';
                $publisherLogoUrl   = $article['publisher_logo_url']    ?? '';

                if ($publisherName || $publisherLogoUrl)
                {
                    $publisher = ['@type' => 'Organization'];

                    if ($publisherName) $publisher['name'] = $article['publisher_name'];

                    if ($publisherLogoUrl)
                    {
                        $imageData = $this->seoGetimage($publisherLogoUrl);
                        $publisher['logo'] = [
                            '@type'     => 'ImageObject',
                            'url'       => $this->grav['uri']->base() . $imageData['url'],
                            'width'     => $imageData['width'],
                            'height'    => $imageData['height'],
                        ];
                    }

                    $microdata['article']['publisher'] = $publisher;
                }

                $imageUrl = $article['image_url'] ?? '';

                if ($imageUrl)
                {
                    $imageData = $this->seoGetimage($imageUrl);
                    $microdata['article']['image']['@type']     = 'ImageObject';
                    $microdata['article']['image']['url']       = $this->grav['uri']->base() . $imageData['url'];
                    $microdata['article']['image']['width']     = $imageData['width'];
                    $microdata['article']['image']['height']    = $imageData['height'];
                }
            }
        }

        $microdata = $this->array_filter_recursive($microdata);

        foreach ($microdata as $key => $value)
        {
            $jsonScript = PHP_EOL . '<script type="application/ld+json">' . PHP_EOL . json_encode($microdata[$key], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT ) . PHP_EOL . '</script>';
            $outputJson = $outputJson . $jsonScript;
        }

        $outputJson = '</script>' . $outputJson . '<script>';
        $this->grav['twig']->twig_vars['json'] = $outputJson;
        $this->grav['twig']->twig_vars['myvar'] = $outputJson;

        if ($outputJson != "</script><script>")
        {
            $assets->addInlineJs($outputJson, 100);
        }
    }

    /**
     * [onBlueprintCreated:0]
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        if (strpos($event['type'], 'modular/') === 0)
        {
            return;
        }

        $blueprint = $event['blueprint'];

        if ($blueprint->get('form/fields/tabs', null, '/'))
        {
            $blueprints = new Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get($this->name);
            $blueprint->extend($extends, true);
        }
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }
}
