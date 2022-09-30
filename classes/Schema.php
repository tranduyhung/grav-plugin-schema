<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Common\Page\Page;

/**
 * Base class for schame types.
 */
abstract class Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    /**
     * Structured data.
     */
    public $data;

    /**
     * Schema type.
     */
    public $type;

    /**
     * @param  object  Grav page.
     */
    public function __construct(Page $page = null)
    {
        $this->grav = Grav::instance();

        $this->data = [
            '@context'  => 'https://schema.org',
            'type'      => $this->type,
        ];

        $this->page = ($page === null) ? $this->grav['page'] : $page;
    }

    /**
     * Generate structured data for a Grav page.
     * 
     * @return array
     */
    abstract public function generateStructuredData();

    /**
     * cleanMarkdown
     * 
     * @param  string  Markdown to clean.
     * 
     * @return string
     */
    public function cleanMarkdown($text): string
    {
        $text = strip_tags($text);
        $rules = [
            '/{%[\s\S]*?%}[\s\S]*?/'                 => '',    // remove twig include
            '/<style(?:.|\n|\r)*?<\/style>/'         => '',    // remove style tags
            '/<script[\s\S]*?>[\s\S]*?<\/script>/'   => '',    // remove script tags
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
    }

    /**
     * Get image's URL and sizes.
     * 
     * @param  string  Image path in Grav.
     * 
     * @return array
     */
    public function getImageData($imagePath): array
    {
        $pattern = '~((\/[^\/]+)+)\/([^\/]+)~';
        $replacement = '$1';
        $fixedUrl = preg_replace($pattern, $replacement, $imagePath);
        $imageName = preg_replace($pattern, '$3', $imagePath);
        $imageArray = $this->grav['page']->find($fixedUrl)->media()->images();
        $keyImages = array_keys($imageArray);
        $imageKey = array_search($imageName, $keyImages);
        $keyValue = $keyImages[$imageKey];
        $imageObject = $imageArray[$keyValue];

        $im = getimagesize($imageObject->path());
 
        return [
            'width'     => "$im[0]",
            'height'    => "$im[1]",
            'url'       => $imageObject->url(),
        ];
    }
}