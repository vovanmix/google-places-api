<?php

return [
    'places' => [
        'key' => env('GOOGLE_PLACES_API_KEY', null),
        'cache_period' => 20160, //minutes; 2 weeks default
        'image_thumbnail_width' => 100,
        'image_thumbnail_height' => 100,
        'image_big_width' => 1024,
        'image_big_height' => 768
    ],
];
