<?php

namespace App\Http\Controllers;

use App\Services\Integration\IntegrationUpsertService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class IntegrationOAuthController extends Controller
{
    public function callback(string $provider, Request $request, IntegrationUpsertService $upsert)
    {
        $state = Cache::get($request->input('state'));

        abort_if(! $state, 404, 'Invalid state');

        $stateOrgId = Arr::get($state, 'organization_id');
        $user = $request->user();

        abort_if(! $user, 401, 'Unauthenticated');

        $isMember = $user->organizations()
            ->wherePivot('organization_id', $stateOrgId)
            ->exists();

        if (! $isMember) {
            Cache::forget($request->input('state'));
            abort(403, 'You are not a member of this organization');
        }

        $uri = new Uri(Arr::get($state, 'redirect_uri'));
        $query = Query::parse($uri->getQuery());

        try {
            $user = Socialite::driver($provider)
                ->stateless()
                ->user();
        } catch (ClientException $e) {
            $query['error'] = $e->getMessage();

            return redirect()->away($uri->withQuery(Query::build($query)));
        } catch (InvalidStateException $e) {
            $query['error'] = 'invalid_state';

            return redirect()->away($uri->withQuery(Query::build($query)));
        }

        $model = $upsert->fromSocialite($user, $state, $provider);

        $uri = new Uri(Arr::get($state, 'redirect_uri'));
        $query = Query::parse($uri->getQuery());
        $query['integration_id'] = $model->getRouteKey();
        $query['integration_type'] = $model->getMorphClass();

        $redirectUrl = $uri->withQuery(Query::build($query));

        return redirect()->away($redirectUrl);
    }
}
