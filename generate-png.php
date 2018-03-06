<?php
require 'vendor/autoload.php';

use Symfony\Component\Process\Process;

$longopts = array(
    'directory:',
    'nis:',
    'copyright:'
);
$options = getopt('d:c:', $longopts);

$directory = (isset($options['d']) ? $options['d'] : (isset($options['directory']) ? $options['directory'] : NULL));
$nis = (isset($options['nis']) ? explode(',', $options['nis']) : array());
$copyright = (isset($options['c']) ? $options['c'] : (isset($options['copyright']) ? $options['copyright'] : NULL));

$directory = realpath($directory);

$glob = glob($directory.'/mapfiles/*.map');
if (!empty($nis)) {
    $mapfiles = array();

    foreach ($glob as $file) {
        $fname = pathinfo($file, PATHINFO_FILENAME);
        if (in_array(substr($fname, 0, 5), $nis)) {
            $mapfiles[] = $file;
        }
    }
} else {
    $mapfiles = $glob;
}

if (!file_exists($directory.'/result') || !is_dir($directory.'/result')) {
    mkdir($directory.'/result');
} else {
    $glob = glob($directory.'/result/*.png');
    foreach ($glob as $file) {
        unlink($file);
    }
}

foreach ($mapfiles as $i => $mapfile) {
    $fname = pathinfo($mapfile, PATHINFO_FILENAME);

    if (preg_match('/^([0-9]{5}) - (.+?) - (.+?)$/', $fname, $matches) === 1) {
        $municipality = TRUE;

        $nis = $matches[1];
        $name_fr = $matches[2];
        $name_nl = $matches[3];
    } else if (preg_match('/^([0-9]{5}) - (.+?)$/', $fname, $matches) === 1) {
        $municipality = TRUE;

        $nis = $matches[1];
        $name_fr = $matches[2];
        $name_nl = $matches[2];
    } else {
        $municipality = FALSE;
    }

    $shp2img = sprintf(
        'shp2img -m %s -o %s',
        escapeshellarg($mapfile),
        escapeshellarg($directory.'/result/'.$fname.'-src.png')
    );

    $process = new Process($shp2img);
    $process->run();

    $scalebar = sprintf(
        'scalebar %s %s',
        escapeshellarg($mapfile),
        escapeshellarg($directory.'/result/'.$fname.'-scale.png')
    );

    $process = new Process($scalebar);
    $process->run();

    list($w, $h) = getimagesize($directory.'/result/'.$fname.'-src.png'); $w += 4; $h += 4;

    $im = imagecreatetruecolor($w, $h);
    imageresolution($im, 72);

    imagealphablending($im, FALSE);
    imagesavealpha($im, TRUE);

    $im1 = imagecreatefrompng($directory.'/result/'.$fname.'-src.png'); $h1 = imagesy($im1); $w1 = imagesx($im1);
    imagecopymerge($im, $im1, 2, 2, 0, 0, $w1, $h1, 100);
    imagedestroy($im1);

    $im2 = imagecreatefrompng($directory.'/result/'.$fname.'-scale.png'); $h2 = imagesy($im2); $w2 = imagesx($im2);
    imagecopymerge($im, $im2, 5, ($h-$h2-5), 0, 0, $w2, $h2, 75);
    imagedestroy($im2);

    $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    $copy = '© '.date('Y').' GEO-6'.(!is_null($copyright) ? ' © '.$copyright : '');
    $bbox = imageftbbox(9, 90, $font, $copy);
    $h3 = abs($bbox[1]) + abs($bbox[5]) + 10;
    $w3 = abs($bbox[0]) + abs($bbox[2]) + 10;
    $im3 = imagecreatetruecolor($w3, $h3);
    imagefilledrectangle($im3, 0, 0, $w3, $h3, imagecolorallocate($im3, 255, 255, 255));
    imagettftext($im3, 9, 90, ($w3-3-$bbox[1]), ($h3-5), imagecolorexact($im3, 80, 80, 80), $font, $copy);
    imagecopymerge($im, $im3, ($w-$w3-5), ($h-$h3-5), 0, 0, $w3, $h3, 75);
    imagedestroy($im3);

    if ($municipality === TRUE) {
        $title = ($name_fr === $name_nl ? $name_fr : $name_fr.PHP_EOL.$name_nl);
        $bbox = imageftbbox(18, 0, $font, $title);
        $h4 = abs($bbox[1]) + abs($bbox[5]) + 10;
        $w4 = abs($bbox[0]) + abs($bbox[2]) + 10;

        if ($name_fr !== $name_nl) {
            $bboxb = imageftbbox(18, 0, $font, $name_fr);
            $h4b = abs($bboxb[1]) + abs($bboxb[5]) + 10;
            $w4b = abs($bboxb[0]) + abs($bboxb[2]) + 10;
        }

        $im4 = imagecreatetruecolor($w4, $h4);
        imagefilledrectangle($im4, 0, 0, $w4, $h4, imagecolorallocate($im4, 255, 255, 255));
        imagettftext($im4, 18, 0, 5, (isset($h4b) ? $h4b-5 : $h4-5), imagecolorexact($im4, 80, 80, 80), $font, $title);
        imagecopymerge($im, $im4, 5, 5, 0, 0, $w4, $h4, 75);
        imagedestroy($im4);
    }

    imagepng($im, $directory.'/result/'.$fname.'.png');
    imagedestroy($im);

    unlink($directory.'/result/'.$fname.'-src.png');
    unlink($directory.'/result/'.$fname.'-scale.png');

    echo sprintf('Progress: %.1f%% : %s', (($i+1) / count($mapfiles) * 100), $fname.'.png').PHP_EOL;
}

exit();
