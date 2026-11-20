<?php

namespace App\Domain\Communications\Services;

final class EmailBannerRenderer
{
    private const FONT_SIZE = 24;

    private const LINE_HEIGHT = 37;

    private const FIRST_BASELINE = 514;

    /** Right edges measured from the reference banner. */
    private const RIGHT_ANCHORS = [2361, 2335];

    /** @var array<string, string> */
    private static array $cache = [];

    /** @var array<string, string> */
    private static array $urlCache = [];

    /** JPEG bytes of the banner with the sender name and position drawn in. */
    public function jpeg(?string $name, ?string $position): string
    {
        $name = trim((string) ($name ?? ''));
        $position = trim((string) ($position ?? ''));
        $cacheKey = $this->cacheKey($name, $position);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $image = @imagecreatefromjpeg($this->sourcePath());

        if ($image === false) {
            $fallback = @file_get_contents($this->sourcePath());

            return self::$cache[$cacheKey] = $fallback === false ? '' : $fallback;
        }

        $font = resource_path('fonts/PlusJakartaSans-400.ttf');
        $white = imagecolorallocate($image, 255, 255, 255);
        $lines = [];

        if ($name !== '') {
            $lines[] = $name.' •';
        }

        if ($position !== '') {
            $lines[] = $position;
        }

        if (is_file($font) && $lines !== []) {
            foreach ($lines as $index => $line) {
                $box = imagettfbbox(self::FONT_SIZE, 0, $font, $line);

                if ($box === false) {
                    continue;
                }

                $width = $box[2] - $box[0];
                $anchor = self::RIGHT_ANCHORS[$index] ?? self::RIGHT_ANCHORS[0];
                $x = $anchor - $width;
                $y = self::FIRST_BASELINE + ($index * self::LINE_HEIGHT);

                imagettftext($image, self::FONT_SIZE, 0, (int) $x, $y, $white, $font, $line);
            }
        }

        ob_start();
        imagejpeg($image, null, 92);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        return self::$cache[$cacheKey] = $jpeg;
    }

    public function dataUri(?string $name, ?string $position): string
    {
        $jpeg = $this->jpeg($name, $position);

        return $jpeg === '' ? '' : 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    /** Short storage URL for CRM previews (avoids sending a large data URI in the Livewire state). */
    public function url(?string $name, ?string $position): string
    {
        $name = trim((string) ($name ?? ''));
        $position = trim((string) ($position ?? ''));
        $cacheKey = $this->cacheKey($name, $position);

        if (isset(self::$urlCache[$cacheKey])) {
            return self::$urlCache[$cacheKey];
        }

        $jpeg = $this->jpeg($name, $position);

        if ($jpeg === '') {
            return self::$urlCache[$cacheKey] = '';
        }

        $directory = storage_path('app/public/email-banners');

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return self::$urlCache[$cacheKey] = '';
        }

        $filename = sha1($cacheKey).'.jpg';
        $file = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! is_file($file) && @file_put_contents($file, $jpeg, LOCK_EX) === false) {
            return self::$urlCache[$cacheKey] = '';
        }

        $version = filemtime($file) ?: 0;

        return self::$urlCache[$cacheKey] = asset('storage/email-banners/'.$filename.'?v='.$version);
    }

    private function sourcePath(): string
    {
        return (string) (config('communications.email_banner.path')
            ?: public_path('images/email-footer-banner.jpg'));
    }

    private function cacheKey(string $name, string $position): string
    {
        return $this->sourcePath().'|'.$name.'|'.$position;
    }
}
