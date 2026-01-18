<?php

namespace Fleetbase\LaravelMysqlSpatial\Types;

use Fleetbase\LaravelMysqlSpatial\Exceptions\InvalidGeoJsonException;
use GeoJson\GeoJson;
use GeoJson\Geometry\Polygon as GeoJsonPolygon;
use GeoJson\Geometry\LinearRing as GeoJsonLinearRing;

class Polygon extends MultiLineString
{
    public function toWKT()
    {
        return sprintf('POLYGON(%s)', (string) $this);
    }

    public static function fromJson($geoJson)
    {
        if (is_string($geoJson)) {
            $geoJson = GeoJson::jsonUnserialize(json_decode($geoJson));
        }

        if (!is_a($geoJson, GeoJsonPolygon::class)) {
            throw new InvalidGeoJsonException('Expected ' . GeoJsonPolygon::class . ', got ' . get_class($geoJson));
        }

        $set = [];
        foreach ($geoJson->getCoordinates() as $coordinates) {
            $points = [];
            foreach ($coordinates as $coordinate) {
                $points[] = new Point($coordinate[1], $coordinate[0]);
            }
            $set[] = new LineString($points);
        }

        return new self($set);
    }

    /**
     * Convert to GeoJson Polygon that is jsonable to GeoJSON.
     * Includes a fix for "LinearRing requires at least four positions".
     *
     * @return GeoJsonPolygon
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $linearRings = [];
        
        foreach ($this->items as $lineString) {
            // Get coordinates from the internal LineString
            $coordinates = $lineString->jsonSerialize()->getCoordinates();

            if (count($coordinates) > 0) {
                // 1. Ensure it is closed (First point must equal Last point)
                $first = $coordinates[0];
                $last = $coordinates[count($coordinates) - 1];

                if ($first !== $last) {
                    $coordinates[] = $first;
                }

                // 2. Ensure at least 4 positions (GeoJSON spec requirement for LinearRing)
                // If it's a triangle but only has 3 points, we repeat the first point until it has 4.
                while (count($coordinates) < 4) {
                    $coordinates[] = $coordinates[0];
                }

                $linearRings[] = new GeoJsonLinearRing($coordinates);
            }
        }

        return new GeoJsonPolygon($linearRings);
    }
}