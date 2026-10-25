<?php

/**
 * Re-encodes generated demo artwork down to shipping weight.
 *
 * The image API answers with PNG regardless of the .jpg the manifest asks for,
 * so every file arrives at 1-2MB with an extension that lies about its bytes.
 * Nothing in the theme displays them anywhere near that size either, so each
 * group is capped at the widest box it can actually occupy on screen and
 * written out as a real JPEG at a quality that survives a retina display
 * without carrying print-grade detail into the repository.
 *
 * Reading goes through imagecreatefromstring so the source format does not
 * matter: whatever the API returned, what lands in the theme is a JPEG.
 *
 *   php docs/tools/optimize-demo.php --src DIR --dest DIR [--dry]
 */


$widths = [
    'hero'       => 1800,  // full-bleed slider, site width
    'gallery'    => 1400,  // product gallery, roughly half the page
    'tile'       => 1200,  // banner tiles, third to half width
    'blog'       => 1200,  // post header
    'category'   => 1200,  // category strip
    'categories' => 1200,  // the demo catalogue's department tiles
    'product'    => 1000,  // product card and quick view
    'products'   => 1200,  // the demo catalogue's own shots, also used at
                           // full size on the product page gallery
    'brands'     => 400,   // a logo mark in a brand strip, never larger
];

$quality = 86;

$src  = '';
$dest = '';
$dry  = false;

for ($i = 1; $i < $argc; $i++) {
    match ($argv[$i]) {
        '--src'  => $src  = rtrim($argv[++$i], '/'),
        '--dest' => $dest = rtrim($argv[++$i], '/'),
        '--dry'  => $dry  = true,
        default  => fwrite(STDERR, "unknown argument {$argv[$i]}\n") && exit(1),
    };
}

if ($src === '' || $dest === '') {
    fwrite(STDERR, "usage: php docs/tools/optimize-demo.php --src DIR --dest DIR [--dry]\n");
    exit(1);
}

if (! is_dir($src)) {
    fwrite(STDERR, "source not found: {$src}\n");
    exit(1);
}

$before = 0;
$after  = 0;
$count  = 0;

foreach (new DirectoryIterator($src) as $dir) {
    if ($dir->isDot() || ! $dir->isDir()) {
        continue;
    }

    $group = $dir->getFilename();
    $width = $widths[$group] ?? 1400;

    foreach (glob($dir->getPathname() . '/*.jpg') as $file) {
        $name = basename($file);
        $out  = "{$dest}/{$group}/{$name}";
        $size = filesize($file);
        $before += $size;

        $image = @imagecreatefromstring((string) file_get_contents($file));

        if (! $image) {
            fwrite(STDERR, "  {$group}/{$name}  UNREADABLE\n");
            continue;
        }

        // PNG sources may carry an alpha channel that JPEG cannot hold. Flatten
        // onto white first so nothing composites against black.
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);
        $image = $flat;

        if (imagesx($image) > $width) {
            $scaled = imagescale($image, $width, -1, IMG_BICUBIC_FIXED);
            imagedestroy($image);
            $image = $scaled;
        }

        if (! $dry) {
            if (! is_dir(dirname($out))) {
                mkdir(dirname($out), 0755, true);
            }

            imagejpeg($image, $out, $quality);
        }

        imagedestroy($image);

        $now = $dry ? $size : filesize($out);
        $after += $now;
        $count++;

        printf(
            "  %-9s %-22s %5dKB -> %4dKB  (%dpx)\n",
            $group,
            $name,
            $size / 1024,
            $now / 1024,
            $width
        );
    }
}

printf(
    "\n%d images, %.1fMB -> %.1fMB%s\n",
    $count,
    $before / 1048576,
    $after / 1048576,
    $dry ? '  (dry run, nothing written)' : ''
);
