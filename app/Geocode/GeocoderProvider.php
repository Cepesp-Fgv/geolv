<?php

namespace GeoLV\Geocode;

use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Provider\BingMaps\BingMaps;
use Geocoder\Provider\Cache\ProviderCache;
use Geocoder\Provider\Provider;
use GeoLV\AddressCollection;
use Geocoder\Location;
use Geocoder\Provider\ArcGISOnline\ArcGISOnline;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\Query\GeocodeQuery;
use GeoLV\Address;
use GeoLV\Geocode\Clusters\ClusterWithScipy;
use GeoLV\Search;
use GeoLV\User;
use Http\Adapter\Guzzle6\Client as GuzzleHttpClientAdapter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Cache\Repository as CacheRepository;

class GeocoderProvider
{
    /** @var Provider */
    private $provider;

    private $adapter;

    private $defaultProviders;
    private $cache;

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
        $this->defaultProviders = ['google_maps', 'here_geocoder'];
        $this->adapter = GuzzleHttpClientAdapter::createWithConfig(['verify' => false]);
    }

    /**
     * @param array|null $providers
     * @param User $user
     */
    public function setProviders(array $providers = null, User $user = null): void
    {
        $providers = empty($providers)? $this->defaultProviders : $providers;
        /** @var User $user */
        $user = $user ?: auth()->user();
        $config = [];

        foreach ($providers as $provider) {
            $service = $this->resolveProvider($provider, $user);
            if (!empty($service))
                $config[] = $service;
        }

        $this->provider = new ProviderCache(new GroupResults($config), $this->cache);
    }

    /**
     * @param $text
     * @param $locality
     * @param $postalCode
     * @return AddressCollection
     */
    public function geocode($text, $locality, $postalCode): AddressCollection
    {
        $search = $this->getSearch($text, $locality, $postalCode);

        return $this->get($search);
    }

    public function get(Search $search): AddressCollection
    {
        $results = $this->geocodeResults($search);

        $sorter = new SortByRelevance($search);
        $results = $sorter->apply($results);

        $groupper = new ClusterWithScipy();
        //$groupper = new ClusterByAverage();
        //$groupper = new ClusterWithKMeans();
        $groupper->apply($results, $search->max_d);

        return $results->values();
    }

    /**
     * @param $text
     * @param $locality
     * @param $postalCode
     * @return Search
     */
    private function getSearch($text, $locality, $postalCode): Search
    {
        return Search::firstOrCreate([
            'text' => filled($text)? $text : null,
            'locality' => filled($locality)? $locality : null,
            'postal_code' => filled($postalCode)? $postalCode : null,
        ]);
    }

    /**
     * @param string $apiKey
     * @return GoogleMaps
     */
    private function getGoogleProvider(string $apiKey): GoogleMaps
    {
        return new GoogleMaps($this->adapter, 'pt-BR', $apiKey);
    }

    /**
     * @return ArcGISOnline
     */
    private function getArcGISOnlineProvider(): ArcGISOnline
    {
        return new ArcGISOnline($this->adapter, 'BRA');
    }

    /**
     * @param string $apiKey
     * @return HereGeocoder
     */
    private function getHereGeocoderProvider(string $apiKey): HereGeocoder
    {
        return new HereGeocoder($this->adapter, $apiKey);
    }

    /**
     * @param string $apiKey
     * @return BingMaps
     */
    private function getBingMapsProvider(string $apiKey): BingMaps
    {
        return new BingMaps($this->adapter, $apiKey);
    }

    /**
     * @param $provider
     * @param User $user
     * @return Provider|null
     */
    private function resolveProvider($provider, User $user): ?Provider
    {
        $config = $user->provider($provider);
        if (empty($config))
            return null;

        switch ($provider) {
            case 'google_maps':
                return $this->getGoogleProvider($config->api_key);
            case 'here_geocoder':
                return $this->getHereGeocoderProvider($config->api_key);
            case 'bing_maps':
                return $this->getBingMapsProvider($config->api_key);
            case 'arcgis_online':
                return $this->getArcGISOnlineProvider();
            default:
                throw new UnsupportedOperation("Unsupported provider $provider.");
        }
    }

    /**
     * @param Search $search
     * @return Collection|AddressCollection
     */
    private function geocodeResults(Search $search)
    {
        $results = $this->provider->geocodeQuery(GeocodeQuery::create($search->address));
        $collection = new AddressCollection();

        /** @var Location $result */
        foreach ($results as $result) {
            $address = Address::firstOrCreate([
                'street_name' => $result->getStreetName(),
                'street_number' => $result->getStreetNumber(),
                'locality' => $this->extractLocality($result),
                'postal_code' => $result->getPostalCode(),
                'sub_locality' => $result->getSubLocality(),
                'country_code' => $result->getCountry()->getCode(),
                'country_name' => $result->getCountry()->getName(),
                'latitude' => $result->getCoordinates()->getLatitude(),
                'longitude' => $result->getCoordinates()->getLongitude(),
                'provider' => $result->getProvidedBy(),
            ]);

            $address->search_id = $search->id;

            try {
                $search->addresses()->attach($address->id);
            } catch (QueryException $exception) {}

            $collection->add($address);
        }

        return $collection;
    }

    /**
     * @param Location $result
     * @return string|null
     */
    private function extractLocality(Location $result)
    {
        $locality = $result->getLocality();
        $adminLevel = $result->getAdminLevels();
        if (blank($locality) && filled($adminLevel)) {

            for ($i = 1; $i <= 5; $i++) {
                if ($adminLevel->has($i)) {
                    $locality = $adminLevel->get($i)->getName();
                }

                if (filled($locality))
                    break;
            }

        }

        return trim(str_replace("State of", "", $locality));
    }

}