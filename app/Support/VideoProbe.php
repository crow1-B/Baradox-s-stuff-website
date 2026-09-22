<?php

namespace App\Support;

use Symfony\Component\Process\Process;

class VideoProbe
{
    private const TIMEOUT = 25.0;

    private const POSTER_WIDTH = 640;

    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        foreach (['ffprobe', 'ffmpeg'] as $binary) {
            $which = new Process(['which', $binary]);
            $which->run();

            if (! $which->isSuccessful()) {
                return self::$available = false;
            }
        }

        return self::$available = true;
    }

    public static function duration(string $path): ?int
    {
        if (! self::available() || ! is_file($path)) {
            return null;
        }

        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);
        $process->setTimeout(self::TIMEOUT);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $seconds = trim($process->getOutput());

        if ($seconds === '' || ! is_numeric($seconds)) {
            return null;
        }

        $rounded = (int) round((float) $seconds);

        return $rounded > 0 ? $rounded : null;
    }

    public static function poster(string $path, string $destination, ?int $duration = null): bool
    {
        if (! self::available() || ! is_file($path)) {
            return false;
        }

        $directory = dirname($destination);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        $seek = ($duration !== null && $duration > 0) ? min($duration * 0.1, 10.0) : 1.0;
        return self::grab($path, $destination, $seek)
            || ($seek > 0 && self::grab($path, $destination, 0.0));
    }

    private static function grab(string $path, string $destination, float $seek): bool
    {
        $process = new Process([
            'ffmpeg',
            '-v', 'error',
            '-y',
            '-ss', (string) round($seek, 3),
            '-i', $path,
            '-frames:v', '1',
            '-vf', 'scale=' . self::POSTER_WIDTH . ':-2',
            '-q:v', '4',
            $destination,
        ]);
        $process->setTimeout(self::TIMEOUT);

        try {
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            @unlink($destination);

            return false;
        }

        return true;
    }
}
