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
        $config = $this->mergeConfig($page);
        $content = strip_tags($page->content());
        $assets = $this->grav['assets'];
        $pattern = '~((\/[^\/]+)+)\/([^\/]+)~';
        $replacement = '$1';
        $outputJson = "";
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.admin.route');
        $microdata = [];

        if (property_exists($page->header(), 'musiceventenabled'))
        {
            if (($page->header()->musiceventenabled)
                && $this->config['plugins']['seo']['music_event_type'])
            {
                $musicEventsArray = $page->header()->musicevents;
 
                if (count($musicEventsArray) > 0)
                {
                    foreach ($musicEventsArray as $event)
                    {
                        if (isset($event['musicevent_performer']))
                        {
                            foreach ($event['musicevent_performer'] as $artist)
                            {
                                $performerarray[] = [
                                    '@type'     => @$artist['performer_type'],
                                    'name'      => @$artist['name'],
                                    'sameAs'    => @$artist['sameAs'], 
                                ];
                            }
                        }

                        if (isset($event['musicevent_workPerformed']))
                        {
                            foreach ($event['musicevent_workPerformed'] as $work)
                            {
                                $workarray[] = [
                                    'name'      => @$work['name'],
                                    'sameAs'    => @$work['sameAs'], 
                                ];
                            }
                        }

                        if (isset($event['musicevent_image']))
                        {
                            $imageUrl = $event['musicevent_image'];
                            $imageData = $this->seoGetimage($imageUrl);
                            $musicEventImage = [
                                '@type'     => 'ImageObject',
                                'width'     => $imageData['width'],
                                'height'    => $imageData['height'],
                                'url'       => $this->grav['uri']->base() .  $imageData['url'],
                            ];
                        }

                        $microdata[] = [
                            '@context'  => 'http://schema.org',
                            '@type'     => 'MusicEvent',
                            'name'      => @$event['musicevent_location_name'],
                            'location'  => [
                                '@type'     => 'MusicVenue',
                                'name'      => @$event['musicevent_location_name'],
                                'address'   => @$event['musicevent_location_address'],
                            ],
                            'description'   => @$event['musicevent_description'],
                            'url'           => @$event['musicevent_url'],
                            'performer'     => @$performerarray,
                            'workPerformed' => @$workarray, 
                            'image'         => @$musicEventImage,
                            'offers'        => [
                                '@type'         => 'Offer',
                                'price'         => @$event['musicevent_offers_price'],
                                'priceCurrency' => @$event['musicevent_offers_priceCurrency'],
                                'url'           => @$event['musicevent_offers_url'], 
                            ],
                            'startDate' => @date("c", strtotime($event['musicevent_startdate'])),
                            'endDate'   => @date("c", strtotime($event['musicevent_enddate'])),
                        ];
                    }
                }
            }
        }

        if (property_exists($page->header(), 'eventenabled'))
        {
            if ($page->header()->eventenabled
                && $this->config['plugins']['seo']['event_type'])
            {
                $eventsArray = @$page->header()->addevent;

                if (count($eventsArray) > 0)
                {
                    foreach ($eventsArray as $event)
                    {
                        $microdata[] = [
                            '@context'  => 'http://schema.org',
                            '@type'     => 'Event',
                            'name'      => @$event['event_name'],
                            'location'  => [
                                '@type'     => 'Place',
                                'name'      => @$event['event_location_name'],
                                'address'   => [
                                    '@type'             => 'PostalAddress',
                                    'addressLocality'   => @$event['event_location_address_addressLocality'],
                                    'addressRegion'     => @$event['event_location_address_addressRegion'],
                                    'streetAddress'     => @$event['event_location_streetAddress'],
                                ],
                                'url' => @$event['musicevent_location_url'],
                            ],
                            'description'   => @$event['musicevent_description'],
                            'offers'        => [
                                '@type'         => 'Offer',
                                'price'         => @$event['event_offers_price'],
                                'priceCurrency' => @$event['event_offers_currency'],
                                'url'           => @$event['event_offers_url'], 
                            ],
                            'startDate'     => @date("c", strtotime($event['event_startDate'])),
                            'endDate'       => @date("c", strtotime($event['event_endDate'])),
                            'description'   => @$event['event_description'],
                        ];
                    }
                }
            }
        }

        if (property_exists($page->header(), 'personenabled'))
        {
            if ($page->header()->personenabled
                && $this->config['plugins']['seo']['person_type'])
                {
                    $personarray = @$page->header()->addperson;

                    if (count($personarray) > 0)
                    {
                        foreach ($personarray as $person)
                        {
                            $microdata[] = [
                                '@context'  => 'http://schema.org',
                                '@type'     => 'Person',
                                'name'      => @$person['person_name'],
                                'address'    => [
                                    '@type'             => 'PostalAddress',
                                    'addressLocality'   => @$person['person_address_addressLocality'],
                                    'addressRegion'     => @$person['person_address_addressRegion'],
                                ],
                                'jobTitle' => @$person['person_jobTitle'],
                            ];
                        }
                    }
                }
            }

            if (property_exists($page->header(), 'orgaenabled'))
            {
                if ($page->header()->orgaenabled
                    && $this->config['plugins']['seo']['organization_type'])
                {
                    if (isset($page->header()->orga['founders']))
                    {
                        foreach ($page->header()->orga['founders'] as $founder)
                        {
                            $founderArray[] = [
                                '@type' => 'Person',
                                'name'  => @$founder['name'],
                            ];
                        }
                    }

                    if (isset($page->header()->orga['similar']))
                    {
                        foreach ($page->header()->orga['similar'] as $similar)
                        {
                            $similarArray[] = $similar['sameas'];
                        }
                    }

                    if (isset($page->header()->orga['areaserved']))
                    {
                        foreach ($page->header()->orga['areaserved'] as $areaServed)
                        {
                            $areaServedArray[] = $areaServed['area'];
                        }
                    }

                    if (isset($page->header()->orga['openingHours']))
                    {
                        foreach ($page->header()->orga['openingHours'] as $hours)
                        {
                            $openingHours[] = $hours['entry'];
                        }
                    }

                    if (isset($page->header()->orga['offercatalog']))
                    {
                        foreach ($page->header()->orga['offercatalog'] as $offer)
                        {
                            if (array_key_exists('offereditem', $offer))
                            {
                                foreach ($offer['offereditem'] as $service)
                                {
                                    $offerArray[] = [
                                        '@type'             => 'OfferCatalog',
                                        'name'              => @$offer['offer'],
                                        'description'       => @$offer['description'],
                                        'url'               => @$offer['url'],
                                        'image'             => @$offer['image'],
                                        'itemListElement'   => [
                                            '@type' => 'Offer',
                                            'itemOffered'   => [
                                                '@type'     => 'Service',
                                                'name'  => @$service['name'],
                                                'url'   => @$service['url'],
                                            ],
                                        ],
                                    ];
                                }
                            } else {

                            $offerArray[] = [
                                '@type'         => 'OfferCatalog',
                                'name'          => @$offer['offer'],
                                'description'   => @$offer['description'],
                                'url'           => @$offer['url'],
                                'image'         => @$offer['image'],
                            ];
                        }
                    }
                }

                if (property_exists($page->header(),'orgaratingenabled'))
                {
                    if ($page->header()->orgaratingenabled)
                    {
                        $orgaRating = [
                            '@type'         => 'AggregateRating',
                            'ratingValue'   => @$page->header()->orga['ratingValue'],
                            'reviewCount'   => @$page->header()->orga['reviewCount'],
                        ];
                    } 
                } 

                $microdata[] = [
                    '@context'      => 'http://schema.org',
                    '@type'         => 'Organization',
                    'name'          => @$page->header()->orga['name'],
                    'legalname'     => @$page->header()->orga['legalname'],
                    'taxid'         => @$page->header()->orga['taxid'],
                    'vatid'         => @$page->header()->orga['vatid'],
                    'areaServed'    => @$areaServedArray,
                    'description'   => @$page->header()->orga['description'],
                    'address'       => [
                        '@type'             => 'PostalAddress',
                        'streetAddress'     => @$page->header()->orga['streetaddress'],
                        'addressLocality'   => @$page->header()->orga['city'],
                        'addressRegion'     => @$page->header()->orga['state'],
                        'postalCode'        => @$page->header()->orga['zipcode'],
                    ],
                    'telephone'         => @$page->header()->orga['phone'],
                    'logo'              => @$page->header()->orga['logo'],
                    'url'               => @$page->header()->orga['url'],
                    'openingHours'      => @$openingHours,
                    'email'             => @$page->header()->orga['email'],
                    'foundingDate'      => @$page->header()->orga['foundingDate'],
                    'aggregateRating'   => @$orgaRating,
                    'paymentAccepted'   => @$page->header()->orga['paymentAccepted'],
                    'founders'          => @$founderArray,
                    'sameAs'            => @$similarArray,
                    'hasOfferCatalog'   => @$offerArray
                ];
            }
        }

        if (property_exists($page->header(),'restaurantenabled'))
        {
            if ($page->header()->restaurantenabled
                && $this->config['plugins']['seo']['restaurant_type'])
            {
                if (isset($page->header()->restaurant['image']))
                {
                    $imageUrl = $page->header()->restaurant['image'];
                    $imageData = $this->seoGetimage($imageUrl);
                    $restaurantImage = [
                        '@type'     => 'ImageObject',
                        'width'     => $imageData['width'],
                        'height'    => $imageData['height'],
                        'url'       => $this->grav['uri']->base() .  $imageData['url'],
                    ];
                }

                $microdata[] = [
                    '@context'  => 'http://schema.org',
                    '@type'     => 'Restaurant',
                    'name'      => @$page->header()->restaurant['name'],
                    'address'   => [
                        '@type'             => 'PostalAddress',
                        'addressLocality'   => @$page->header()->restaurant['address_addressLocality'],
                        'addressRegion'     => @$page->header()->restaurant['address_addressRegion'],
                        'streetAddress'     => @$page->header()->restaurant['address_streetAddress'],
                        'postalCode'        => @$page->header()->restaurant['address_postalCode'],
                    ],
                    'areaserved'    => @$areaServedArray,
                    'servesCuisine' => @$page->header()->restaurant['servesCuisine'],
                    'priceRange'    => @$page->header()->restaurant['priceRange'],
                    'image'         => @$restaurantImage,
                    'telephone'     => @$page->header()->restaurant['telephone'],
                ];
            }
        }

        if (property_exists($page->header(),'productenabled'))
        {
            if ($page->header()->productenabled
                && $this->config['plugins']['seo']['product_type'])
            {
                if (isset($page->header()->product['image']))
                {
                    $productImageArray = []; 
                    $productImages = $page->header()->product['image'];

                    foreach ($productImages as $key => $value)
                    {
                        $imageArray = $productImages[$key];

                        foreach($imageArray as $newKey => $newValue)
                        {
                            $imageData = $this->seoGetimage($imageArray[$newKey]);
                            $productImage[] = $this->grav['uri']->base() .  $imageData['url'];
                        }
                    }
                }

                if (isset($page->header()->product['addoffer']))
                {
                    $offers = $page->header()->product['addoffer'];

                    foreach ($offers as $key => $value)
                    {
                        $offer[$key] = [
                            '@type'             => 'Offer',
                            'priceCurrency'     => @$offers[$key]['offer_priceCurrency'],
                            'price'             => @$offers[$key]['offer_price'],
                            'validFrom'         => @$offers[$key]['offer_validFrom'],
                            'priceValidUntil'   => @$offers[$key]['offer_validUntil'],
                            'availability'      => @$offers[$key]['offer_availability'],
                        ];
                    }
                }
                else
                {
                    $offer = '';
                }

                $microdata[] = [
                    '@context'  => 'http://schema.org',
                    '@type'     => 'Product',
                    'name'      => @$page->header()->product['name'],
                    'category'  => @$page->header()->product['category'],
                    'brand'     => [
                        '@type' => 'Thing',
                        'name'  => @$page->header()->product['brand'],
                    ],
                    'offers'            => $offer,
                    'description'       => @$page->header()->product['description'],
                    'image'             => @$productImage,
                    'aggregateRating'   => [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => @$page->header()->product['ratingValue'],
                        'reviewCount' => @$page->header()->product['reviewCount'],
                      
                    ]
                ];
            }
        }

        if (property_exists($page->header(), 'articleenabled'))
        {
            if (isset($page->header()->article['headline']))
            {
               $headline =  $page->header()->article['headline'];
            }
            else
            {
                $headline = $page->title();
            }

            if ($page->header()->articleenabled
                && $this->config['plugins']['seo']['article_type'])
            {
                $microdata['article'] = [
                    '@context'          => 'http://schema.org',
                    '@type'             => 'Article',
                    'headline'          => @$headline ,
                    'mainEntityOfPage'  => [
                        "@type" => "WebPage",
                        'url'   => $this->grav['uri']->base(),
                    ],
                    'articleBody'   =>  @$this->cleanMarkdown($content),
                    'datePublished' => @date("c", $page->date()),
                    'dateModified'  => @date("c", $page->modified()),
                ];

                if (isset($page->header()->article['description']))
                {
                    $microdata['article']['description'] = $page->header()->article['description'];
                }
                else
                {
                    $microdata['article']['description'] = substr($content, 0, 140);
                }

                if (isset($page->header()->article['author']))
                {
                    $microdata['article']['author'] = $page->header()->article['author'];
                }
 
                if (isset($page->header()->article['publisher_name']))
                {
                    $microdata['article']['publisher']['@type'] = 'Organization';
                    $microdata['article']['publisher']['name']  = @$page->header()->article['publisher_name'];
                }

                if (isset($page->header()->article['publisher_logo_url']))
                {
                    $publisherlogourl = $page->header()->article['publisher_logo_url'];
                    $imageData = $this->seoGetimage($publisherlogourl);
                    $microdata['article']['publisher']['logo']['@type']     = 'ImageObject';
                    $microdata['article']['publisher']['logo']['url']       = $this->grav['uri']->base() . $imageData['url'];
                    $microdata['article']['publisher']['logo']['width']     = $imageData['width'];
                    $microdata['article']['publisher']['logo']['height']    = $imageData['height'];

                }
 
                if (isset($page->header()->article['image_url']))
                {
                    $imageUrl = $page->header()->article['image_url'];
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
