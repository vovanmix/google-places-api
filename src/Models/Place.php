<?php
namespace Vovanmix\GoogleApi\Models;
use Illuminate\Support\Collection;

/**
 * Class Place
 * @package Vovanmix\GoogleApi\Models
 *
 */
class Place {
    
    public $id = '';
    public $name = '';
    public $address = '';
    public $description = '';
    public $phone = '';
    public $website = '';
    public $google_url = '';
    public $price_level = '';
    public $rating = 0;
    public $ratings_count = 0;
    public $open_now = false;
    public $open_now_hours = '';
    public $opening_hours = '';
    public $address_components = [];
    public $geometry = [];
    public $icon = '';
    /** @var Review[] | Collection */
    public $reviews;
    /** @var Photo[] | Collection */
    public $photos;
    
}