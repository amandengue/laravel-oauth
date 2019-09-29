<?php

namespace Mckenziearts\LaravelOAuth\Traits;

use Laravel\Socialite\Facades\Socialite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

trait OAuthSocialite
{
    /**
     * Redirect the user to the Provider authentication page.
     *
     * @param $provider
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToProvider($provider)
    {
        $provider = strtolower($provider);

        if ($provider === 'facebook') {
            return Socialite::driver('facebook')->with(['auth_type' => 'rerequest'])->redirect();
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from provider.
     *
     * @param $provider
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback($provider)
    {
        $providerUser = Socialite::driver($provider)->user();

        // Check if user email is null
        // Get user from the database with the good provider_id or email
        if (is_null($providerUser->getEmail())) {
            $this->redirectToProvider($provider);
        }

        $user = DB::table(config('laravel-oauth.users.table'))
            ->where($provider.'_id', '=', $providerUser->getId())
            ->orWhere('email', '=', $providerUser->getEmail())
            ->first();

        if (is_null($user)) {
            // Save user to the database
            $userId = $this->registerUser($provider, $providerUser);
            Auth::loginUsingId($userId);

            return redirect()->intended($this->redirectPath());
        }

        // Login user
        Auth::loginUsingId($user->id);

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Resgiter user to the database and return ID.
     *
     * @param $provider
     * @param $user
     * @return int
     */
    public function registerUser($provider, $user)
    {
        $userId = DB::table(config('laravel-oauth.users.table'))->insertGetId([
            'name'      => $user->getName(),
            'email'     => $user->getEmail(),
            'password'  => bcrypt('password'),
            $provider.'_id'     => $user->getId(),
            'email_verified_at' => Carbon::now(),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);

        return $userId;
    }
}
