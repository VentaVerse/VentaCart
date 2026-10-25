<?php

namespace App\Support;

/**
 * One lock for every change to the package folders: uploads, and the
 * Install, Update, Enable, Disable and Uninstall buttons.
 *
 * It is an flock() on a file in storage, not a cache lock, so it holds
 * across processes whatever cache store the store runs, and the operating
 * system releases it when the process that held it ends, even when PHP
 * dies on a fatal error or is killed. Stores with several web servers must
 * keep storage/framework on a shared disk for the lock to cover them all.
 */
final class PackageLock
{
    /** @var resource|null */
    private $handle;

    /** @param resource $handle */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /** The lock file, from extensions.upload.lock_file. */
    public static function path(): string
    {
        return (string) (config('extensions.upload.lock_file') ?: storage_path('framework/package-changes.lock'));
    }

    /**
     * Takes the lock, waiting up to $waitSeconds for another holder to
     * finish. Returns null when it is still held.
     */
    public static function acquire(float $waitSeconds = 0): ?self
    {
        $path = self::path();
        $folder = dirname($path);
        if (! is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }

        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new \RuntimeException('The package lock file cannot be opened.');
        }

        $deadline = microtime(true) + max(0, $waitSeconds);

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return new self($handle);
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(100000);
        } while (true);

        fclose($handle);

        return null;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
