<?php

require 'vendor/autoload.php';

use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

define('DEFAULT_MODE', 'municipality');
define('DEFAULT_SIZE_UNITS', 'pxl');
define('DEFAULT_SIZE_X', 1190);
define('DEFAULT_SIZE_Y', 842);
define('DEFAULT_SCALEDENOM', 15000);

$longopts = [
    'map:',
    'directory:',
    'mode:',
    'size_units:',
    'size_x:',
    'size_y:',
    'extent:',
    'scale_denom:',
];
$options = getopt('m:d:u:x:y:e:s:', $longopts);

$map = (isset($options['m']) ? $options['m'] : (isset($options['map']) ? $options['map'] : null));
$directory = (isset($options['d']) ? $options['d'] : (isset($options['directory']) ? $options['directory'] : dirname($map)));
$mode = (isset($options['mode']) ? $options['mode'] : DEFAULT_MODE);
$size_units = (isset($options['u']) ? $options['u'] : (isset($options['size_units']) ? $options['size_units'] : DEFAULT_SIZE_UNITS));
$size_x = (isset($options['x']) ? $options['x'] : (isset($options['size_x']) ? $options['size_x'] : DEFAULT_SIZE_X));
$size_y = (isset($options['y']) ? $options['y'] : (isset($options['size_y']) ? $options['size_y'] : DEFAULT_SIZE_Y));
$extent = (isset($options['e']) ? explode(' ', $options['e']) : (isset($options['extent']) ? explode(' ', $options['extent']) : null));
$scale_denom = (isset($options['s']) ? $options['s'] : (isset($options['scale_denom']) ? $options['scale_denom'] : DEFAULT_SCALEDENOM));

$directory = realpath($directory);

if (!file_exists($directory.'/mapfiles') || !is_dir($directory.'/mapfiles')) {
    mkdir($directory.'/mapfiles');
} else {
    $glob = glob($directory.'/mapfiles/*.map');
    foreach ($glob as $file) {
        unlink($file);
    }
}

switch ($size_units) {
    case 'mm':
        $size = [
            ($size_x / 10) * 72 / 2.54,
            ($size_y / 10) * 72 / 2.54,
        ];
        break;
    case 'cm':
        $size = [
            $size_x * 72 / 2.54,
            $size_y * 72 / 2.54,
        ];
        break;
    case 'pxl':
    default:
        $size = [
            $size_x,
            $size_y,
        ];
        break;
}

if (!is_null($map) && file_exists($map) && is_readable($map)) {
    $proj4 = new Proj4php();

    $proj3857 = new Proj('EPSG:3857', $proj4);
    $proj4326 = new Proj('EPSG:4326', $proj4);

    $default = file_get_contents($map);
    $default = str_replace('%DIR%', $directory, $default);

    switch ($mode) {
        case 'grid':
            $default = str_replace('%SIZE%', implode(' ', $size), $default);

            $bottomleft = new Point($extent[0], $extent[1], $proj4326);
            $_bottomleft = $proj4->transform($proj3857, $bottomleft);
            $topright = new Point($extent[2], $extent[3], $proj4326);
            $_topright = $proj4->transform($proj3857, $topright);

            $dx = round(($size[0] * 2.54 / 72) / 100 * $scale_denom);
            $dy = round(($size[1] * 2.54 / 72) / 100 * $scale_denom);

            echo sprintf('Mode "grid" : %d m. × %d m.', $dx, $dy).PHP_EOL;
            echo sprintf('Extent (EPSG:4326) : %s', implode(' ', $extent)).PHP_EOL;
            echo sprintf('Extent (EPSG:3857) : %f %f %f %f', $_bottomleft->x, $_bottomleft->y, $_topright->x, $_topright->y).PHP_EOL;

            $cursor_x = 0;

            for ($x = $_bottomleft->x; $x <= $_topright->x; $x += $dx) {
                $cursor_y = 0;

                echo 'X='.$x.' X\'='.($x + $dx).PHP_EOL;
                for ($y = $_bottomleft->y; $y <= $_topright->y; $y += $dy) {
                    echo 'Y='.$y.' Y\'='.($y + $dy).PHP_EOL;

                    $new = str_replace('%EXTENT%', $x.' '.$y.' '.($x + $dx).' '.($y + $dy), $default);

                    file_put_contents($directory.'/mapfiles/'.$cursor_x.'-'.$cursor_y.'.map', $new);

                    $cursor_y++;
                }
                $cursor_x++;
            }

            break;

        case 'municipality':
        default:
            echo 'Mode "municipality"'.PHP_EOL;

            $json = json_decode(file_get_contents(__DIR__.'/data/municipality-extent.json'), true);
            $count = count($json); $c = 1;

            foreach ($json as $id => $r) {
                $bottomleft = new Point($r['xmin'], $r['ymin'], $proj4326);
                $_bottomleft = $proj4->transform($proj3857, $bottomleft);
                $topright = new Point($r['xmax'], $r['ymax'], $proj4326);
                $_topright = $proj4->transform($proj3857, $topright);

                $new = str_replace('%EXTENT%', ($_bottomleft->x - 500).' '.($_bottomleft->y - 500).' '.($_topright->x + 500).' '.($_topright->y + 500), $default);
                $new = str_replace('%NIS%', $id, $new);

                if ($size[0] == 0 && $size[1] == 0) {
                    $_size_x = (($_topright->x - $_bottomleft->x) + 1000) / $scale_denom * (1 / (2.54 / 72 / 100));
                    $_size_y = (($_topright->y - $_bottomleft->y) + 1000) / $scale_denom * (1 / (2.54 / 72 / 100));

                    $new = str_replace('%SIZE%', round($_size_x).' '.round($_size_y), $new);
                } else {
                    $new = str_replace('%SIZE%', round($size_x).' '.round($size_y), $new);
                }

                $fname = $id.' - '.($r['name_fr'] === $r['name_nl'] ? $r['name_fr'] : $r['name_fr'].' - '.$r['name_nl']).'.map';

                file_put_contents($directory.'/mapfiles/'.$fname, $new);
                echo sprintf('Progress: %.1f%% : %s', ($c / $count * 100), $fname).PHP_EOL;
                $c++;
            }
            break;
    }
}

exit();
