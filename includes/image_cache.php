<?php
function getCachedProfileImage(
    string $photoURL,
    string $cacheFolder  = __DIR__ . '/../cache/',   // filesystem
    string $cacheUrlBase = '../cache/',                // public URL
    int    $ttl          = 3600                      // freshness in seconds
): string {
    // ── Guard: nothing to cache ─────────────────────────
    $photoURL = trim($photoURL);
    if ($photoURL === '') {
        // No URL provided — return empty or your default avatar:
        return '';
        // Or, if you have a default image:
        // return '/assets/img/default-avatar.png';
    }

    // Ensure cache folder exists
    if (! is_dir($cacheFolder)) {
        mkdir($cacheFolder, 0755, true);
    }

    // If it already points to our cache, just return it
    if (strpos($photoURL, $cacheUrlBase) === 0) {
        return $photoURL;
    }

    // Build filename and paths
    $filename  = 'profile_' . md5($photoURL) . '.jpg';
    $cachePath = rtrim($cacheFolder, '/\\') . DIRECTORY_SEPARATOR . $filename;  
    $cacheUrl  = rtrim($cacheUrlBase, '/\\') . '/' . $filename;              

    // If the cached file is fresh, use it
    if (file_exists($cachePath) && (time() - filemtime($cachePath)) < $ttl) {
        return $cacheUrl;
    }

    // Try downloading & caching
    $data = @file_get_contents($photoURL);
    if ($data !== false && @file_put_contents($cachePath, $data) !== false) {
        return $cacheUrl;
    }

    // fallback to pulling straight from remote
    return $photoURL;
}


?>
