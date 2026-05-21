<?php

namespace App\Database;

use DeviceDetector\Cache\CacheInterface;
use Illuminate\Support\Facades\Cache;

class DeviceDetectorCache implements CacheInterface
{
    public function fetch(string $id)
    {
        return Cache::driver('file')->get($id);
    }

    /**
     * @inheritDoc
     */
    public function contains(string $id): bool
    {
        return Cache::driver('file')->has($id);
    }

    /**
     * @inheritDoc
     */
    public function save(string $id, $data, int $lifeTime = 0): bool
    {
        return Cache::driver('file')->put($id, $data, \func_num_args() < 3 ? null : $lifeTime);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $id): bool
    {
        return Cache::driver('file')->forget($id);
    }

    /**
     * @inheritDoc
     */
    public function flushAll(): bool
    {
        return Cache::driver('file')->flush();
    }
}
