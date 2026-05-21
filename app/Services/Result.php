<?php

namespace App\Services;

/**
 * @template T
 */
#[\AllowDynamicProperties]
class Result
{
    /**
     * @var T
     */
    private $value;

    /**
     * @param  T  $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        // success or failure?
        // source = Context::get('source')
        // source = 'api', 'web',
    }

    /**
     * @return T
     */
    public function getValue()
    {
        return $this->value;
    }

    public static function notFound()
    {
        //         $this->failWithError('NotFoundFailure')
    }
}
