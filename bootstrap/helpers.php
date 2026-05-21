<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Returns the caller of this method nicely formatted for logging.
 * Will return a string like `MyController@index.argument`
 *
 * @param  mixed  ...$arguments
 */
function logname(...$arguments): string
{
    $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) {
        return ! in_array($trace['function'], ['logname']);
    });

    $callerName = class_basename(! is_null($caller) ? $caller['class'] : null);
    $callerFn = ! is_null($caller) ? $caller['function'] : null;

    return trim($callerName.'@'.$callerFn.Str::start(implode('.', Arr::flatten($arguments)), '.'), '.\\,@');
}

function attempt(Closure $closure) {
    try {
        return $closure();
    } catch (\Throwable $e) {
        return null;
    }
}
