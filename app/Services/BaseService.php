<?php

namespace App\Services;

class BaseService
{
    public static function make()
    {
        return app(static::class);
    }

    public function call(...$arguments)
    {
        return static::make(...$arguments);
    }

    public static function dispatch(...$arguments): void
    {
        $class = static::class;
        dispatch(fn () => $class::make()->__invoke(...$arguments));
    }

    public static function dispatch_sync(...$arguments): mixed
    {
        $class = static::class;

        return dispatch_sync(fn () => $class::make()->__invoke(...$arguments));
    }
}
