<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

/**
 * Laravel's Vite::isRunningHot() only checks that public/hot exists — it
 * never verifies the dev server behind it is actually reachable. If that
 * process dies without the file being cleaned up (closed terminal,
 * crashed process, machine sleep), every page keeps shipping <script>
 * tags for a dead port and the app renders blank. This adds a fast
 * reachability check so a stale hot file falls back to the compiled
 * build in public/build instead of breaking the page.
 */
class ResilientVite extends Vite
{
    private ?bool $hotServerReachable = null;

    public function isRunningHot()
    {
        if (! is_file($this->hotFile())) {
            return false;
        }

        return $this->hotServerReachable ??= $this->hotServerIsReachable();
    }

    private function hotServerIsReachable(): bool
    {
        $url = rtrim(file_get_contents($this->hotFile()));

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https') ? 443 : 80);

        if (! $host) {
            return false;
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, 0.2);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
