<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class Person extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'Person';
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
        $person = $schema['person'] ?? [];

        if (empty($person)) return [];

        $data = $this->data;

        $name = $person['name'] ?? '';
        if ($name) $data['name'] = $name;

        $jobTitle = $person['job_title'] ?? '';
        if ($jobTitle) $data['jobTitle'] = $jobTitle;

        $addressLocality    = $person['address_address_locality'] ?? '';
        $addressRegion      = $person['address_address_region']   ?? '';

        if ($addressLocality || $addressRegion)
        {
            $address = ['@type' => 'PostalAddress'];

            if ($addressLocality)   $address['addressLocality'] = $addressLocality;
            if ($addressRegion)     $address['addressRegion']   = $addressRegion;

            $data['address'] = $address;
        }

        return $data;
    }
}