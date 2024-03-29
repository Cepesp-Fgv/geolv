<?php

namespace GeoLV\Geocode;


use Geocoder\Collection;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroupResults implements Provider
{
    /**
     * @var Provider[]
     */
    private $providers = [];

    /**
     * @param Provider[] $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * @param GeocodeQuery $query
     *
     * @return Collection
     *
     * @throws \Geocoder\Exception\Exception
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $list = [];

        foreach ($this->providers as $provider) {
            try {
                $results = $provider->geocodeQuery($query);
                if (!$results->isEmpty()) {
                    foreach ($results as $result)
                        $list[] = $result;
                }
            } catch (InvalidCredentials $e) {
                $this->providers = collect($this->providers)->reject($provider)->values()->toArray();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return new AddressCollection($list);
    }

    /**
     * @param ReverseQuery $query
     *
     * @return Collection
     *
     * @throws \Geocoder\Exception\Exception
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
        $list = [];

        foreach ($this->providers as $provider) {
            try {
                $result = $provider->reverseQuery($query);
                if (!$result->isEmpty())
                    $list[] = $result;

            } catch (\Throwable $e) {
                //
            }
        }

        return new AddressCollection($list);
    }

    /**
     * Returns the provider's name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'group';
    }
}