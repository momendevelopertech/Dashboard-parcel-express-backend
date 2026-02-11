<?php

namespace App\Traits;

trait TransformsCoordinates
{
    protected function fixPolygonCoordinateShipment(string $wkt): string
    {
        $pattern = '/-?\d+\.?\d*\s+-?\d+\.?\d*/';
        
        return preg_replace_callback($pattern, function($matches) {
            $coords = explode(' ', $matches[0]);
            
            // If coordinates are in lng lat shipment, swap them
            if ($this->isLngLatPair($coords[0], $coords[1])) {
                return "{$coords[1]} {$coords[0]}";
            }
            
            return $matches[0];
        }, $wkt);
    }

    protected function isLngLatPair($first, $second): bool
    {
        $lng = (float)$first;
        $lat = (float)$second;
        
        return abs($lng) <= 180 && abs($lat) <= 90;
    }
}