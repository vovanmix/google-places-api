<?php
namespace Vovanmix\GoogleApi\Models;

use Carbon\Carbon;

/**
 * Class Review
 * @package Vovanmix\GoogleApi\Models
 * 
 */
class Review {

    public $id = '';
    public $author_name = '';
    public $profile_photo_url = '';
    public $text = '';
    /** @var Carbon */
    public $time;
    public $rating = 0;
    public $aspects = [];
    public $language = '';

}