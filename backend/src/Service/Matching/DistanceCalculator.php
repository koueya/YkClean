<?php

namespace App\Service\Matching;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DistanceCalculator
{
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;
    private ?string $googleApiKey;
    private ?string $geocodingProvider;
    private int $cacheTtl = 86400; // 24 heures

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        ?string $googleApiKey = null,
        string $geocodingProvider = 'openstreetmap' // 'google' ou 'openstreetmap'
    ) {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->googleApiKey = $googleApiKey;
        $this->geocodingProvider = $geocodingProvider;
    }

    /**
     * Calculer la distance entre deux points (formule de Haversine)
     */
    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        string $unit = 'km'
    ): float {
        $earthRadius = $unit === 'km' ? 6371 : 3959; // km ou miles

        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Calculer la distance entre deux adresses
     */
    public function calculateDistanceFromAddresses(
        string $address1,
        string $address2,
        string $unit = 'km'
    ): ?float {
        $coords1 = $this->geocodeAddress($address1);
        $coords2 = $this->geocodeAddress($address2);

        if (!$coords1 || !$coords2) {
            return null;
        }

        return $this->calculateDistance(
            $coords1['latitude'],
            $coords1['longitude'],
            $coords2['latitude'],
            $coords2['longitude'],
            $unit
        );
    }

    /**
     * Géocoder une adresse pour obtenir les coordonnées
     */
    public function geocodeAddress(string $address): ?array
    {
        if (empty(trim($address))) {
            return null;
        }

        // Vérifier le cache
        $cacheKey = 'geocode_' . md5($address);
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($address) {
                $item->expiresAfter($this->cacheTtl);

                if ($this->geocodingProvider === 'google' && $this->googleApiKey) {
                    return $this->geocodeWithGoogle($address);
                } else {
                    return $this->geocodeWithOpenStreetMap($address);
                }
            });
        } catch (\Exception $e) {
            $this->logger->error('Geocoding failed', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Géocoder avec Google Maps API
     */
    private function geocodeWithGoogle(string $address): ?array
    {
        try {
            $url = sprintf(
                'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s',
                urlencode($address),
                $this->googleApiKey
            );

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $location = $data['results'][0]['geometry']['location'];
                $formattedAddress = $data['results'][0]['formatted_address'];

                $this->logger->info('Google geocoding successful', [
                    'address' => $address,
                    'formatted' => $formattedAddress,
                ]);

                return [
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'formatted_address' => $formattedAddress,
                    'provider' => 'google',
                ];
            }

            $this->logger->warning('Google geocoding returned no results', [
                'address' => $address,
                'status' => $data['status'],
            ]);

            return null;

        } catch (\Exception $e) {
            $this->logger->error('Google geocoding error', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Géocoder avec OpenStreetMap (Nominatim)
     */
    private function geocodeWithOpenStreetMap(string $address): ?array
    {
        try {
            $url = sprintf(
                'https://nominatim.openstreetmap.org/search?q=%s&format=json&limit=1',
                urlencode($address)
            );

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'ServicePlatform/1.0',
                ],
            ]);

            $data = $response->toArray();

            if (!empty($data)) {
                $result = $data[0];

                $this->logger->info('OpenStreetMap geocoding successful', [
                    'address' => $address,
                    'formatted' => $result['display_name'],
                ]);

                return [
                    'latitude' => (float)$result['lat'],
                    'longitude' => (float)$result['lon'],
                    'formatted_address' => $result['display_name'],
                    'provider' => 'openstreetmap',
                ];
            }

            $this->logger->warning('OpenStreetMap geocoding returned no results', [
                'address' => $address,
            ]);

            return null;

        } catch (\Exception $e) {
            $this->logger->error('OpenStreetMap geocoding error', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Géocodage inversé (coordonnées vers adresse)
     */
    public function reverseGeocode(float $latitude, float $longitude): ?array
    {
        $cacheKey = sprintf('reverse_geocode_%f_%f', $latitude, $longitude);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($latitude, $longitude) {
                $item->expiresAfter($this->cacheTtl);

                if ($this->geocodingProvider === 'google' && $this->googleApiKey) {
                    return $this->reverseGeocodeWithGoogle($latitude, $longitude);
                } else {
                    return $this->reverseGeocodeWithOpenStreetMap($latitude, $longitude);
                }
            });
        } catch (\Exception $e) {
            $this->logger->error('Reverse geocoding failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Géocodage inversé avec Google
     */
    private function reverseGeocodeWithGoogle(float $latitude, float $longitude): ?array
    {
        try {
            $url = sprintf(
                'https://maps.googleapis.com/maps/api/geocode/json?latlng=%f,%f&key=%s',
                $latitude,
                $longitude,
                $this->googleApiKey
            );

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $result = $data['results'][0];

                return [
                    'address' => $result['formatted_address'],
                    'components' => $this->extractAddressComponents($result['address_components']),
                    'provider' => 'google',
                ];
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('Google reverse geocoding error', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Géocodage inversé avec OpenStreetMap
     */
    private function reverseGeocodeWithOpenStreetMap(float $latitude, float $longitude): ?array
    {
        try {
            $url = sprintf(
                'https://nominatim.openstreetmap.org/reverse?lat=%f&lon=%f&format=json',
                $latitude,
                $longitude
            );

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'ServicePlatform/1.0',
                ],
            ]);

            $data = $response->toArray();

            if (!empty($data) && isset($data['display_name'])) {
                return [
                    'address' => $data['display_name'],
                    'components' => [
                        'city' => $data['address']['city'] ?? $data['address']['town'] ?? $data['address']['village'] ?? null,
                        'postal_code' => $data['address']['postcode'] ?? null,
                        'country' => $data['address']['country'] ?? null,
                        'street' => $data['address']['road'] ?? null,
                    ],
                    'provider' => 'openstreetmap',
                ];
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('OpenStreetMap reverse geocoding error', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extraire les composants d'adresse de Google
     */
    private function extractAddressComponents(array $components): array
    {
        $extracted = [
            'street_number' => null,
            'street' => null,
            'city' => null,
            'postal_code' => null,
            'country' => null,
        ];

        foreach ($components as $component) {
            $types = $component['types'];

            if (in_array('street_number', $types)) {
                $extracted['street_number'] = $component['long_name'];
            } elseif (in_array('route', $types)) {
                $extracted['street'] = $component['long_name'];
            } elseif (in_array('locality', $types)) {
                $extracted['city'] = $component['long_name'];
            } elseif (in_array('postal_code', $types)) {
                $extracted['postal_code'] = $component['long_name'];
            } elseif (in_array('country', $types)) {
                $extracted['country'] = $component['long_name'];
            }
        }

        return $extracted;
    }

    /**
     * Vérifier si un point est dans un rayon donné
     */
    public function isWithinRadius(
        float $centerLat,
        float $centerLon,
        float $pointLat,
        float $pointLon,
        float $radius,
        string $unit = 'km'
    ): bool {
        $distance = $this->calculateDistance(
            $centerLat,
            $centerLon,
            $pointLat,
            $pointLon,
            $unit
        );

        return $distance <= $radius;
    }

    /**
     * Trouver tous les points dans un rayon donné
     */
    public function findPointsWithinRadius(
        float $centerLat,
        float $centerLon,
        array $points,
        float $radius,
        string $unit = 'km'
    ): array {
        $results = [];

        foreach ($points as $point) {
            if (!isset($point['latitude']) || !isset($point['longitude'])) {
                continue;
            }

            $distance = $this->calculateDistance(
                $centerLat,
                $centerLon,
                $point['latitude'],
                $point['longitude'],
                $unit
            );

            if ($distance <= $radius) {
                $results[] = array_merge($point, [
                    'distance' => $distance,
                ]);
            }
        }

        // Trier par distance
        usort($results, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $results;
    }

    /**
     * Calculer les coordonnées d'une bounding box
     */
    public function calculateBoundingBox(
        float $latitude,
        float $longitude,
        float $radius,
        string $unit = 'km'
    ): array {
        $earthRadius = $unit === 'km' ? 6371 : 3959;

        // Conversion en radians
        $latRad = deg2rad($latitude);
        $lonRad = deg2rad($longitude);
        $radiusRad = $radius / $earthRadius;

        // Calcul des limites
        $minLat = rad2deg($latRad - $radiusRad);
        $maxLat = rad2deg($latRad + $radiusRad);

        $deltaLon = asin(sin($radiusRad) / cos($latRad));
        $minLon = rad2deg($lonRad - $deltaLon);
        $maxLon = rad2deg($lonRad + $deltaLon);

        return [
            'min_latitude' => $minLat,
            'max_latitude' => $maxLat,
            'min_longitude' => $minLon,
            'max_longitude' => $maxLon,
        ];
    }

    /**
     * Calculer la durée de trajet estimée
     */
    public function estimateTravelTime(
        float $fromLat,
        float $fromLon,
        float $toLat,
        float $toLon,
        string $mode = 'driving' // driving, walking, cycling, transit
    ): ?array {
        if ($this->geocodingProvider !== 'google' || !$this->googleApiKey) {
            // Estimation basique si pas de Google API
            $distance = $this->calculateDistance($fromLat, $fromLon, $toLat, $toLon);
            
            // Vitesses moyennes (km/h)
            $speeds = [
                'driving' => 50,
                'walking' => 5,
                'cycling' => 15,
                'transit' => 30,
            ];

            $speed = $speeds[$mode] ?? $speeds['driving'];
            $durationMinutes = ($distance / $speed) * 60;

            return [
                'distance' => $distance,
                'duration_minutes' => round($durationMinutes),
                'estimated' => true,
            ];
        }

        try {
            $url = sprintf(
                'https://maps.googleapis.com/maps/api/directions/json?origin=%f,%f&destination=%f,%f&mode=%s&key=%s',
                $fromLat,
                $fromLon,
                $toLat,
                $toLon,
                $mode,
                $this->googleApiKey
            );

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            if ($data['status'] === 'OK' && !empty($data['routes'])) {
                $route = $data['routes'][0];
                $leg = $route['legs'][0];

                return [
                    'distance' => $leg['distance']['value'] / 1000, // Conversion en km
                    'duration_minutes' => round($leg['duration']['value'] / 60),
                    'duration_text' => $leg['duration']['text'],
                    'distance_text' => $leg['distance']['text'],
                    'estimated' => false,
                ];
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('Travel time estimation error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calculer le point central d'un ensemble de coordonnées
     */
    public function calculateCentroid(array $coordinates): ?array
    {
        if (empty($coordinates)) {
            return null;
        }

        $x = 0;
        $y = 0;
        $z = 0;

        foreach ($coordinates as $coord) {
            $lat = deg2rad($coord['latitude']);
            $lon = deg2rad($coord['longitude']);

            $x += cos($lat) * cos($lon);
            $y += cos($lat) * sin($lon);
            $z += sin($lat);
        }

        $count = count($coordinates);
        $x /= $count;
        $y /= $count;
        $z /= $count;

        $lon = atan2($y, $x);
        $hyp = sqrt($x * $x + $y * $y);
        $lat = atan2($z, $hyp);

        return [
            'latitude' => rad2deg($lat),
            'longitude' => rad2deg($lon),
        ];
    }

    /**
     * Valider des coordonnées
     */
    public function validateCoordinates(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90 &&
               $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Formater une distance pour l'affichage
     */
    public function formatDistance(float $distance, string $unit = 'km'): string
    {
        if ($unit === 'km') {
            if ($distance < 1) {
                return round($distance * 1000) . ' m';
            } else {
                return round($distance, 1) . ' km';
            }
        } else {
            return round($distance, 1) . ' miles';
        }
    }

    /**
     * Obtenir la distance la plus courte d'un point vers plusieurs destinations
     */
    public function findNearestPoint(
        float $fromLat,
        float $fromLon,
        array $destinations
    ): ?array {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($destinations as $destination) {
            if (!isset($destination['latitude']) || !isset($destination['longitude'])) {
                continue;
            }

            $distance = $this->calculateDistance(
                $fromLat,
                $fromLon,
                $destination['latitude'],
                $destination['longitude']
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = array_merge($destination, [
                    'distance' => $distance,
                ]);
            }
        }

        return $nearest;
    }

    /**
     * Calculer l'itinéraire optimal (problème du voyageur de commerce simplifié)
     */
    public function optimizeRoute(
        array $startPoint,
        array $waypoints,
        ?array $endPoint = null
    ): array {
        if (empty($waypoints)) {
            return [];
        }

        $route = [];
        $remaining = $waypoints;
        $current = $startPoint;

        // Algorithme glouton: toujours prendre le point le plus proche
        while (!empty($remaining)) {
            $nearest = $this->findNearestPoint(
                $current['latitude'],
                $current['longitude'],
                $remaining
            );

            if ($nearest) {
                $route[] = $nearest;
                $current = $nearest;

                // Retirer de la liste
                $remaining = array_filter($remaining, function ($point) use ($nearest) {
                    return $point !== $nearest;
                });
                $remaining = array_values($remaining);
            } else {
                break;
            }
        }

        // Ajouter le point final si spécifié
        if ($endPoint) {
            $distance = $this->calculateDistance(
                $current['latitude'],
                $current['longitude'],
                $endPoint['latitude'],
                $endPoint['longitude']
            );
            $route[] = array_merge($endPoint, ['distance' => $distance]);
        }

        // Calculer la distance totale
        $totalDistance = array_sum(array_column($route, 'distance'));

        return [
            'route' => $route,
            'total_distance' => round($totalDistance, 2),
            'waypoints_count' => count($waypoints),
        ];
    }

    /**
     * Nettoyer le cache de géocodage
     */
    public function clearCache(?string $address = null): void
    {
        if ($address) {
            $cacheKey = 'geocode_' . md5($address);
            $this->cache->delete($cacheKey);
        } else {
            // Nettoyer tout le cache (si implémenté par le cache adapter)
            $this->logger->info('Cache clearing requested for all geocoding entries');
        }
    }
}