<?php
// TEMPORARY placeholder icon so the app builds before the official MbunieEduHub
// artwork is added. Drop the real logo at assets/icon/mhub_icon.png (1024x1024)
// and run:  dart run flutter_launcher_icons
//
// Usage: php tool/make_icon.php

$S = 1024;
$img = imagecreatetruecolor($S, $S);
imagealphablending($img, true);
imagesavealpha($img, true);

$blue  = imagecolorallocate($img, 0x2B, 0x3A, 0x8C);
$white = imagecolorallocate($img, 0xFF, 0xFF, 0xFF);
$cyan  = imagecolorallocate($img, 0x37, 0xA6, 0xE0);

imagefilledrectangle($img, 0, 0, $S, $S, $blue);

$fonts = [
    'C:/Windows/Fonts/ariblk.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/segoeuib.ttf',
];
$font = null;
foreach ($fonts as $f) { if (is_file($f)) { $font = $f; break; } }

if ($font) {
    $size = 620;
    $bbox = imagettfbbox($size, 0, $font, 'M');
    $tw = $bbox[2] - $bbox[0];
    $th = $bbox[1] - $bbox[7];
    $x = (int) (($S - $tw) / 2 - $bbox[0]);
    $y = (int) (($S - $th) / 2 - $bbox[7]);
    imagettftext($img, $size, 0, $x, $y, $white, $font, 'M');
} else {
    // no font available – fall back to a plain block M
    imagesetthickness($img, 150);
    imageline($img, 300, 740, 300, 320, $white);
    imageline($img, 300, 320, 512, 560, $white);
    imageline($img, 512, 560, 724, 320, $white);
    imageline($img, 724, 320, 724, 740, $white);
}

// small cyan accent, top-left
imagefilledpolygon($img, [260, 250, 340, 220, 320, 330, 235, 305], $cyan);

// rounded corners
$radius = 180;
$bg = imagecolorallocatealpha($img, 0, 0, 0, 127);
foreach ([[0,0,1,1],[1,0,-1,1],[0,1,1,-1],[1,1,-1,-1]] as [$qx,$qy,$dx,$dy]) {
    $ox = $qx ? $S - $radius : $radius;
    $oy = $qy ? $S - $radius : $radius;
    for ($px = 0; $px <= $radius; $px++) {
        for ($py = 0; $py <= $radius; $py++) {
            if ($px * $px + $py * $py > $radius * $radius) {
                imagesetpixel($img, $ox + $dx * $px, $oy + $dy * $py, $bg);
            }
        }
    }
}

$dir = __DIR__ . '/../assets/icon';
if (!is_dir($dir)) mkdir($dir, 0777, true);
imagepng($img, $dir . '/mhub_icon.png');
echo "wrote assets/icon/mhub_icon.png\n";
