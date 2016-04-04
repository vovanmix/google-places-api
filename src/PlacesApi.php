<?php
namespace Vovanmix\GoogleApi;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException;
use Vovanmix\GoogleApi\Models\Photo;
use Vovanmix\GoogleApi\Models\Place;
use Illuminate\Database\Eloquent\Collection;
use Vovanmix\GoogleApi\Models\PlacePreview;
use Vovanmix\GoogleApi\Models\Prediction;
use Vovanmix\GoogleApi\Models\Review;
use Cache;

class PlacesApi
{
    const NEARBY_SEARCH_URL = 'nearbysearch/json';

    const TEXT_SEARCH_URL = 'textsearch/json';

    const RADAR_SEARCH_URL = 'radarsearch/json';

    const DETAILS_SEARCH_URL = 'details/json';

    const PLACE_AUTOCOMPLETE_URL = 'autocomplete/json';

    const QUERY_AUTOCOMPLETE_URL = 'queryautocomplete/json';

    const PHOTO_DETAILS_URL = 'photo';

    /**
     * @var
     */
    public $status;

    /**
     * @var null
     */
    private $key = null;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * PlacesApi constructor.
     *
     * @param null $key
     */
    public function __construct($key = null)
    {
        $this->key = $key;

        $this->client = new Client([
            'base_uri' => 'https://maps.googleapis.com/maps/api/place/',
        ]);
    }

    /**
     * Place Nearby Search Request to google api.
     *
     * @param $location
     * @param null $radius
     * @param array $params
     *
     * @return \Illuminate\Support\Collection
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    public function nearbySearch($location, $radius = null, $params = [])
    {
        $this->checkKey();

        $cache_key = 'places.nearby_search.' . md5(json_encode([$location, $radius, $params]));

        $value = Cache::remember($cache_key, config('google.places.cache_period'), function() use ($location, $radius, $params) {
            $params = $this->prepareNearbySearchParams($location, $radius, $params);
            $response = $this->makeRequest(self::NEARBY_SEARCH_URL, $params);

            return $this->convertPlacesCollection($response);
        });

        return $value;
    }

    /**
     * Place Text Search Request to google places api.
     *
     * @param $query
     * @param array $params
     *
     * @return \Illuminate\Support\Collection
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    public function textSearch($query, $params = [])
    {
        $this->checkKey();

        $cache_key = 'places.text_search.' . md5(json_encode([$query, $params]));

        $value = Cache::remember($cache_key, config('google.places.cache_period'), function() use ($query, $params) {

            $params['query'] = $query;
            $response = $this->makeRequest(self::TEXT_SEARCH_URL, $params);

            return $this->convertPlacesCollection($response);
        });

        return $value;
        
    }

    /**
     * Radar Search Request to google api
     *
     * @param $location
     * @param $radius
     * @param $params
     *
     * @return \Illuminate\Support\Collection
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    public function radarSearch($location, $radius, array $params)
    {
        $this->checkKey();

        $cache_key = 'places.radar_search.' . md5(json_encode([$location, $radius, $params]));

        $value = Cache::remember($cache_key, config('google.places.cache_period'), function() use ($location, $radius, $params) {

            $params = $this->prepareRadarSearchParams($location, $radius, $params);

            $response = $this->makeRequest(self::RADAR_SEARCH_URL, $params);

            return $this->convertPlacesCollection($response);
        });

        return $value;
    }

    /**
     * Place Details Request to google places api.
     *
     * @param $placeId
     * @param array $params
     *
     * @return \Illuminate\Support\Collection
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    public function placeDetails($placeId, $params = [])
    {
        $this->checkKey();

        $cache_key = 'places.place_details.' . md5(json_encode([$placeId, $params]));

        $value = Cache::remember($cache_key, config('google.places.cache_period'), function() use ($placeId, $params) {

            $params['placeid'] = $placeId;

            $response = $this->makeRequest(self::DETAILS_SEARCH_URL, $params);

            return $this->convertDetails($response);
        });

        return $value;
    }

    /**
     * Place AutoComplete Request to google places api.
     *
     * @param $input
     * @param array $params
     *
     * @return \Illuminate\Support\Collection
     */
    public function placeAutocomplete($input, $params = [])
    {
        $this->checkKey();

        $cache_key = 'places.place_autocomplete.' . md5(json_encode([$input, $params]));

        $value = Cache::remember($cache_key, config('google.places.cache_period'), function() use ($input, $params) {

            $params['input'] = $input;

            $response = $this->makeRequest(self::PLACE_AUTOCOMPLETE_URL, $params);

            return $this->convertPredictions($response);
        });

        return $value;
    }

    /**
     * Query AutoComplete Request to the google api.
     *
     * @param $input
     * @param array $params
     *
     * @return \Illuminate\Support\Collection
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    public function queryAutocomplete($input, $params = [])
    {
        $this->checkKey();

        $cache_key = 'places.query_autocomplete.' . md5(json_encode([$input, $params]));

        $value = Cache::remember($cache_key, config('google.places.cache_period'), function() use ($input, $params) {

            $params['input'] = $input;

            $response = $this->makeRequest(self::QUERY_AUTOCOMPLETE_URL, $params);

            return $this->convertPredictions($response);
        });

        return $value;
    }

    /**
     * Get Photo Url
     *
     * @param string $photoReference
     * @param null | integer $maxWidth
     * @param null | integer $maxHeight
     * @return mixed
     * @throws GooglePlacesApiException
     */
    public function photoUrl($photoReference, $maxWidth = null, $maxHeight = null)
    {
        $this->checkKey();

        $params['photo_reference'] = $photoReference;
        $params['maxheight'] = $maxHeight;
        $params['maxwidth'] = $maxWidth;

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->getRequest(self::PHOTO_DETAILS_URL, $params);

        return $request->getUri();
    }

    private function getRequest($uri, $params){
        $options = [
            'query' => [
                'key' => $this->key,
            ],
        ];

        $options['query'] = array_merge($options['query'], $params);

        $request = $this->client->get($uri, $options);

        return $request;
    }

    /**
     * @param $uri
     * @param $params
     *
     * @return mixed|string
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    private function makeRequest($uri, $params)
    {

        $request = $this->getRequest($uri, $params);

        $response = json_decode($request->getBody()->getContents(), true);

        $this->setStatus($response['status']);

        if ($response['status'] !== 'OK') {
            throw new GooglePlacesApiException("Response returned with status: "
                . $response['status']);
        }

        return $response;
    }

    /**
     * @param array $raw_data
     * @return Collection
     */
    private function convertPredictions(array $raw_data){
        /** @var \Illuminate\Support\Collection $data */
        $data = collect($raw_data);

        $result = $data->get('predictions');

        $predictions = new Collection();

        foreach($result as $result_item) {
            $prediction = new Prediction();

            $prediction->id = $result_item['id'];
            $prediction->description = @$result_item['description'];
            $prediction->place_id = @$result_item['place_id'];

            $predictions->add($prediction);
        }

        return $predictions;
    }

    /**
     * @param array $raw_data
     * @return Collection
     */
    private function convertPlacesCollection(array $raw_data){
        /** @var \Illuminate\Support\Collection $data */
        $data = collect($raw_data);

        $result = $data->get('results');

        $results = new Collection();

        foreach($result as $result_item) {
            $place = new PlacePreview();

            $place->id = @$result_item['place_id'];
            $place->name = @$result_item['name'];
            $place->price_level = @$result_item['price_level'];
            $place->rating = @$result_item['rating'];
            $place->address = @$result_item['formatted_address'];
            if(!empty($result_item['geometry'])) {
                $place->lat = @$result_item['geometry']['location']['lat'];
                $place->lng = @$result_item['geometry']['location']['lng'];
            }
            if(!empty($result_item['opening_hours'])) {
                $place->open_now = (bool)$result_item['opening_hours']['open_now'];
            }

            $results->add($place);
        }

        return $results;
    }

    /**
     * @param array $raw_data
     * @return Place
     */
    private function convertDetails(array $raw_data){
        /** @var \Illuminate\Support\Collection $data */
        $data = collect($raw_data);

        $result = $data->get('result');

        $place = new Place();

        $place->id = @$result['place_id'];
        $place->name = @$result['name'];
        $place->address = @$result['formatted_address'];
        $place->phone = @$result['formatted_phone_number'];
        $place->website = @$result['website'];
        $place->google_url = @$result['url'];
        $place->price_level = @$result['price_level'];
        $place->rating = @$result['rating'];
        $place->ratings_count = @$result['user_ratings_total'];

        //todo: premium data
        //aspects
        $place->description = @$result['review_summary'];

        $place->open_now_hours = '';
        $place->opening_hours = '';
        $place->open_now = false;
        if (!empty($result['opening_hours'])) {
            $place->opening_hours = @$result['opening_hours']['weekday_text'];
            $place->open_now = (bool)$result['opening_hours']['open_now'];
            $week_day = date("w");
            if (!empty($result['opening_hours']['weekday_text'][$week_day])) {
                $place->open_now_hours = explode(': ', $result['opening_hours']['weekday_text'][$week_day])[1];
            }
        }

        $place->geometry = @$result['geometry'];
        $place->address_components = @$result['address_components'];

        //process photos
        $place->photos = new Collection();

        if(!empty($result['photos'])) {
            foreach ($result['photos'] as $photoInfo) {
                $photo = new Photo();

                $reference = $photoInfo['photo_reference'];

                //todo: create new converters
                //todo: find
                //todo: autocomplete
                //todo: call correct converters


                $photo->max_height = @$photoInfo['height'];
                $photo->max_width = @$photoInfo['width'];
                $photo->thumbnail_url = $this->photoUrl($reference, config('google.places.image_thumbnail_width'), config('google.places.image_thumbnail_height'));
                $photo->big_url = $this->photoUrl($reference, config('google.places.image_big_width'), config('google.places.image_big_height'));

                $place->photos->add($photo);
            }
        }

        //process reviews
        $place->reviews = new Collection();

        if(!empty($result['reviews'])) {
            foreach ($result['reviews'] as $reviewInfo) {

                $review = new Review();

                $review->author_name = $reviewInfo['author_name'];
                $review->profile_photo_url = @$reviewInfo['profile_photo_url'];
                $review->text = $reviewInfo['text'];
                $review->rating = @$reviewInfo['rating'];
                $review->time = Carbon::createFromTimestamp(@$reviewInfo['time']);
                $review->aspects = @$reviewInfo['aspects'];

                $place->reviews->add($review);
            }
        }

        return $place;
    }

    /**
     * @param array $data
     * @param null $index
     * @return \Illuminate\Support\Collection
     */
    private function convertToCollection(array $data, $index = null)
    {
        $data = collect($data);

        if ($index) {
            $data[$index] = collect($data[$index]);
        }

        return $data;
    }

    /**
     * @param mixed $status
     */
    private function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return null
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param null $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    private function checkKey()
    {
        if (!$this->key) {
            throw new GooglePlacesApiException('API KEY is not specified.');
        }
    }

    /**
     * Prepare the params for the Place Search.
     *
     * @param $location
     * @param $radius
     * @param $params
     *
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     * @return mixed
     */
    private function prepareNearbySearchParams($location, $radius, $params)
    {
        $params['location'] = $location;
        $params['radius'] = $radius;

        if (array_key_exists('rankby', $params)
            AND $params['rankby'] === 'distance'
        ) {
            unset($params['radius']);

            if (!array_any_keys_exists(['keyword', 'name', 'types'], $params)) {
                throw new GooglePlacesApiException("Nearby Search require one"
                    . " or more of 'keyword', 'name', or 'types' params since 'rankby' = 'distance'.");
            }
        } elseif (!$radius) {
            throw new GooglePlacesApiException("'radius' param is not defined.");
        }

        return $params;
    }

    /**
     * @param $location
     * @param $radius
     * @param $params
     *
     * @return mixed
     * @throws \Vovanmix\GoogleApi\Exceptions\GooglePlacesApiException
     */
    private function prepareRadarSearchParams($location, $radius, $params)
    {
        $params['location'] = $location;
        $prams['radius'] = $radius;

        if (!array_any_keys_exists(['keyword', 'name', '$type'], $params)) {
            throw new GooglePlacesApiException("Radar Search require one"
                . " or more of 'keyword', 'name', or 'types' params.");
        }

        return $params;
    }
}
