<?php
namespace Grav\Plugin\Schema;

use Grav\Common\Grav;
use Grav\Plugin\Schema\Schema;

class MusicAlbum extends Schema
{
    /**
     * Grav instance.
     */
    protected $grav;

    public function __construct()
    {
        $this->type = 'MusicAlbum';
        parent::__construct();
    }

    /**
     * Generate structured data. Return an array of schema items.
     * 
     * @return array
     */
    public function generateStructuredData(): array
    {
        return [];
    }
}