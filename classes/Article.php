<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class Article extends Schema
{
    public function __construct()
    {
        $this->type = 'Article';
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
        $baseUrl = $this->grav['uri']->base();
        $data = $this->data;
        $page = $this->page;
        $header = $page->header();
        $schema = $header->schema ?? [];
        $content = strip_tags($page->content());

        $data['mainEntityOfPage'] = [
            '@type' => 'WebPage',
            'url'   => $baseUrl,
        ];

        $data['articleBody']    = $this->cleanMarkdown($content);
        $data['datePublished']  = date('c', $page->date());
        $data['dateModified']   = date('c', $page->modified());

        $article = $schema['article'] ?? null;

        if (is_array($article))
        {
            if (isset($article['headline']) && $article['headline'])
            {
                $data['headline'] =  $article['headline'];
            }
            else
            {
                $data['headline'] = $page->title();
            }

            if (isset($article['description']) && $article['description'])
            {
                $data['description'] = $article['description'];
            }
            else
            {
                $data['description'] = substr($content, 0, 140);
            }

            if (isset($article['author']) && $article['author'])
            {
                $data['author'] = $article['author'];
            }

            $publisherName      = $article['publisher_name']        ?? '';
            $publisherLogoUrl   = $article['publisher_logo_url']    ?? '';

            if ($publisherName || $publisherLogoUrl)
            {
                $publisher = ['@type' => 'Organization'];

                if ($publisherName) $publisher['name'] = $article['publisher_name'];

                if ($publisherLogoUrl)
                {
                    $imageData = $this->getImageData($publisherLogoUrl);
                    $publisher['logo'] = [
                        '@type'     => 'ImageObject',
                        'url'       => $baseUrl . $imageData['url'],
                        'width'     => $imageData['width'],
                        'height'    => $imageData['height'],
                    ];
                }

                $data['publisher'] = $publisher;
            }

            $imageUrl = $article['image_url'] ?? '';

            if ($imageUrl)
            {
                $imageData = $this->getImageData($imageUrl);
                $data['image']['@type']     = 'ImageObject';
                $data['image']['url']       = $baseUrl . $imageData['url'];
                $data['image']['width']     = $imageData['width'];
                $data['image']['height']    = $imageData['height'];
            }
        }

        $returnedData[] = $data;

        return $returnedData;
    }
}