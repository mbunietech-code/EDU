<?php
// Generates a placeholder MHub launcher icon (1024x1024) until the real logo PNG is supplied.
// Usage: php tool/make_icon.php
$size = 1024;
$img = imagecreatetruecolor($size, $size);
imagesavealpha($img, true);
imagealphablending($img, true);

// transparent base
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

// vertical blue gradient (deep navy -> bright blue)
for ($y = 0; $y < $size; $y++) {
    $t = $y / $size;
    $r = (int) round(10 + $t * 20);
    $g = (int) round(35 + $t * 120);
    $b = (int) round(90 + $t * 150);
    $col = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $size, $y, $col);
}

// rounded-corner mask: clear the corners
$radius = 200;
$bg = imagecolorallocatealpha($img, 0, 0, 0, 127);
function clear_corner($img, $cx, $cy, $radius, $bg, $quadrant) {
    for ($x = 0; $x <= $radius; $x++) {
        for ($y = 0; $y <= $radius; $y++) {
            if (($x * $x + $y * $y) > $radius * $radius) {
                $px = $quadrant[0] ? $cx + $x : $cx - $x;
                $py = $quadrant[1] ? $cy + $y : $cy - $y;
                imagesetpixel($img, $px, $py, $bg);
            }
        }
    }
}
clear_corner($img, $radius, $radius, $radius, $bg, [false, false]);
clear_corner($img, $size - $radius - 1, $radius, $radius, $bg, [true, false]);
clear_corner($img, $radius, $size - $radius - 1, $radius, $bg, [false, true]);
clear_corner($img, $size - $radius - 1, $size - $radius - 1, $radius, $bg, [true, true]);

// Draw a big "M" using thick polygons
$white = imagecolorallocate($img, 255, 255, 255);
$accent = imagecolorallocate($img, 120, 200, 255);

$thick = 120;
$topY = 300;
$botY = 720;
$leftX = 250;
$rightX = 774;
$midX = 512;
$midY = 540;

imagesetthickness($img, $thick);
// left vertical
imageline($img, $leftX, $botY, $leftX, $topY, $white);
// left diagonal to middle
imageline($img, $leftX, $topY, $midX, $midY, $white);
// right diagonal from middle
imageline($img, $midX, $midY, $rightX, $topY, $white);
// right vertical
imageline($img, $rightX, $topY, $rightX, $botY, $white);

// "HUB" wordmark
$text = "HUB";
$fontSize = 5;
// use built-in large font scaled by drawing string then rely on gd font 5
// Draw with imagestring (small) centered near bottom
$fw = imagefontwidth(5) * strlen($text);
imagestring($img, 5, (int) (($size - $fw) / 2), 800, $text, $accent);

$outDir = __DIR__ . '/../assets/icon';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
imagepng($img, $outDir . '/mhub_icon.png');
imagedestroy($img);
echo "wrote assets/icon/mhub_icon.png\n";
