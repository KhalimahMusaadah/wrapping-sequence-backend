<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pointlocation {
    var $pointOnVertex = true; // Check if the point sits exactly on one of the vertices?

    function __construct() {
        // nothing to init
    }

    /**
     * Cek posisi point terhadap polygon
     * @param string $point "x y"
     * @param array $polygon array of points ["x y", "x y", ...] tutup loop terakhir = pertama
     * @param bool $pointOnVertex apakah point yang tepat di vertex dianggap inside
     * @return string "inside", "outside", "boundary", "vertex"
     */
    function pointInPolygon($point, $polygon, $pointOnVertex = true) {
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

        for ($i=1; $i < $vertices_count; $i++) {
            $vertex1 = $vertices[$i-1]; 
            $vertex2 = $vertices[$i];

            // horizontal boundary
            if ($vertex1['y'] == $vertex2['y'] && $vertex1['y'] == $point['y'] 
                && $point['x'] > min($vertex1['x'], $vertex2['x']) 
                && $point['x'] < max($vertex1['x'], $vertex2['x'])) { 
                return "boundary";
            }

            // ray casting
            if ($point['y'] > min($vertex1['y'], $vertex2['y']) 
                && $point['y'] <= max($vertex1['y'], $vertex2['y']) 
                && $point['x'] <= max($vertex1['x'], $vertex2['x']) 
                && $vertex1['y'] != $vertex2['y']) { 

                $xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) 
                           / ($vertex2['y'] - $vertex1['y'] + 0.0000001) + $vertex1['x']; 

                if ($xinters == $point['x']) {
                    return "boundary";
                }

                if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters) {
                    $intersections++; 
                }
            } 
        } 

        return ($intersections % 2 != 0) ? "inside" : "outside";
    }

    // cek apakah point tepat di vertex
    function pointOnVertex($point, $vertices) {
        foreach($vertices as $vertex) {
            if ($point['x'] == $vertex['x'] && $point['y'] == $vertex['y']) {
                return true;
            }
        }
        return false;
    }

    // transform "x y" string menjadi array ['x'=>..., 'y'=>...]
    function pointStringToCoordinates($pointString) {
        $coordinates = explode(" ", $pointString);
        return array(
            "x" => floatval($coordinates[0]),
            "y" => floatval($coordinates[1])
        );
    }
}


/*$polygon = [
    "-61.04 3.800",  // kiri bawah
    "-61.04 6.2838", // kiri atas
    "-59.698 6.2838",// kanan atas
    "-59.698 3.800", // kanan bawah
    "-61.04 3.800"   // tutup loop
];

