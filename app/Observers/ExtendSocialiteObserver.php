<?php

namespace App\Observers;

use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Stripe\Provider as StripeSocialiteProvider;

class ExtendSocialiteObserver
{
    public function __invoke(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('stripe_test', StripeSocialiteProvider::class);
        $socialiteWasCalled->extendSocialite('stripe', StripeSocialiteProvider::class);
    }
}
