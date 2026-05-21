<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class CreateIntegrationAuthorizationController extends Controller
{
    public function __invoke(string $provider, Request $request)
    {
        if (! in_array($provider, ['stripe', 'stripe_test'])) {
            abort(404);
        }

        /* @var AbstractProvider $socialite */
        $socialite = Socialite::driver($provider)
            ->stateless();

        $user = $request->user();

        $targetUrl = new Uri($socialite->redirect()->getTargetUrl());
        $query = Query::parse($targetUrl->getQuery());
        $state = Arr::get($query, 'state', $request->get('state', Str::random(32)));
        $redirectUri = url('/integrations/'.$provider.'/callback');
        $query['redirect_uri'] = $redirectUri;
//        $query['redirect_uri'] = $provider === 'stripe'
//            ? Str::replace('http://', 'https://', $redirectUri)
//            : $redirectUri;

        $query['state'] = $state;

        $targetUrl = $targetUrl->withQuery(Query::build($query));

        Cache::put($state, [
            'organization_id' => $user->organization_id,
            'redirect_uri' => $request->get('redirect_uri'),
        ], now()->addMinutes(30));

        return [
            'url' => $targetUrl,
        ];
    }
}
