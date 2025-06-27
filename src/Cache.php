<?php

namespace WP_GA4;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Cache
{
    private $cache;

    public function __construct($ttl = 3600, $directory = null) {
        #Log::debug('[CACHE] Cache initialized with TTL ' . $ttl . ', directory ' . $directory);
        $this->cache = new FilesystemAdapter('', $ttl, $directory);
    }

    public function get(callable $callback)
    {
        #Log::info('[CACHE] Trying getting from cache...');
        // Вызываемое будет выполнено только в случае неудачи кэша.
        return $this->cache->get('ga4_report', function () use ($callback) {
            #Log::info('[CACHE] Cache empty');
            return $callback();
        });
    }

}