<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class pointLocation
{
    var $pointOnVertex = true; // Check if the point sits exactly on one of the vertices?

    function pointLocation() {}

    function pointInPolygon($point, $polygon, $pointOnVertex = true)
    {
        $this->pointOnVertex = $pointOnVertex;

        // Transform string coordinates into arrays with x and y values
        $point = $this->pointStringToCoordinates($point);
        $vertices = array();
        foreach ($polygon as $vertex) {
            $vertices[] = $this->pointStringToCoordinates($vertex);
        }

        // Check if the point sits exactly on a vertex
        if ($this->pointOnVertex == true and $this->pointOnVertex($point, $vertices) == true) {
            return "vertex";
        }

        // Check if the point is inside the polygon or on the boundary
        $intersections = 0;
        $vertices_count = count($vertices);

        for ($i = 1; $i < $vertices_count; $i++) {
            $vertex1 = $vertices[$i - 1];
            $vertex2 = $vertices[$i];
            if ($vertex1['y'] == $vertex2['y'] and $vertex1['y'] == $point['y'] and $point['x'] > min($vertex1['x'], $vertex2['x']) and $point['x'] < max($vertex1['x'], $vertex2['x'])) { // Check if point is on an horizontal polygon boundary
                return "boundary";
            }
            if ($point['y'] > min($vertex1['y'], $vertex2['y']) and $point['y'] <= max($vertex1['y'], $vertex2['y']) and $point['x'] <= max($vertex1['x'], $vertex2['x']) and $vertex1['y'] != $vertex2['y']) {
                $xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) / ($vertex2['y'] - $vertex1['y']) + $vertex1['x'];
                if ($xinters == $point['x']) { // Check if point is on the polygon boundary (other than horizontal)
                    return "boundary";
                }
                if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters) {
                    $intersections++;
                }
            }
        }
        // If the number of edges we passed through is odd, then it's in the polygon. 
        if ($intersections % 2 != 0) {
            return "inside";
        } else {
            return "outside";
        }
    }

    function pointOnVertex($point, $vertices)
    {
        foreach ($vertices as $vertex) {
            if ($point == $vertex) {
                return true;
            }
        }
    }

    function pointStringToCoordinates($pointString)
    {
        $coordinates = explode(" ", $pointString);
        return array("x" => $coordinates[0], "y" => $coordinates[1]);
    }

    //hitung jarak euclidean antara 2 titik
    function calculateDistance($point1, $point2)
    {
        $dx = $point2['x'] - $point1['x'];
        $dy = $point2['y'] - $point1['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Cek FMR sudah keluar dari zona wrapping
     * @param string $currentPos posisi FMR saat ini "x y"
     * @param string $previousPos posisi FMR sebelumnya "x y" (untuk deteksi arah)
     * @param string $wrappingPoint titik wrapping "x y" (default: "-60.37 4.603")
     * @param float $radius radius zona wrapping (default: "1.3")
     */

    function checkFMRWrappingZone($currentPos, $previousPos = null, $wrappingPoint = "-60.37 4.603", $radius = 1.3)
    {
        $current = $this->pointStringToCoordinates($currentPos);
        $wrapping = $this->pointStringToCoordinates($wrappingPoint);

        $currentDistance = $this->calculateDistance($current, $wrapping);
        $isInside = $currentDistance < $radius;

        $direction = 'unknown';
        $shouldWrap = false;

        if ($previousPos !== null) {
            $previous = $this->pointStringToCoordinates($previousPos);
            $previousDistance = $this->calculateDistance($previous, $wrapping);

            //untuk mengetahui arah masuk atau keluar FMR
            if ($currentDistance < $previousDistance - 0.01) {
                $direction = 'approaching';
            } elseif ($currentDistance > $previousDistance + 0.01) {
                $direction = 'leaving';
            } else {
                $direction = 'static';
            }
        }

        /**
         * logic masih salah
         */
        if ($isInside && $direction === 'leaving') {
            $shouldWrap = true;
        }

        return [
            'status' => $isInside ? 'inside' : 'outside',
            'direction' => $direction,
            'distance' => round($currentDistance, 3),
            'should_wrap' => $shouldWrap,
            'is_inside_zone' => $isInside
        ];
    }
}

/*$pointLocation = new pointLocation();
$points = array("-12.91 -25.3","5.3 21.03","-8.45 -15.78","-25.41 -1.82");
$polygon = array("-7.63 -15.32","-24.99 -3.47","25.23 69.57","42.33 57.82","-7.63 -15.32");
// The last point's coordinates must be the same as the first one's, to "close the loop"
foreach($points as $key => $point) {
    echo "point " . ($key+1) . " ($point): " . $pointLocation->pointInPolygon($point, $polygon) . "\n";
}*/
