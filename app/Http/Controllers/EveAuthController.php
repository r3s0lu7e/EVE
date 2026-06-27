<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;

class EveAuthController extends Controller
{
    /**
     * Scopes requested from EVE SSO. Wallet read is the one that matters;
     * structures resolves citadel names, markets reads open orders, assets
     * reads the character's item list for net-worth valuation.
     */
    private const SCOPES = [
        'publicData',
        'esi-wallet.read_character_wallet.v1',
        'esi-universe.read_structures.v1',
        'esi-markets.read_character_orders.v1',
        'esi-assets.read_assets.v1',
    ];

    public function redirect()
    {
        // Keep the redirect stateful so the `state` param is sent — EVE SSO
        // requires it. Only the callback is stateless (below), which skips the
        // session-based state comparison that fails on the localhost round-trip.
        return Socialite::driver('eveonline')
            ->scopes(self::SCOPES)
            ->redirect();
    }

    public function callback()
    {
        // Tolerate small clock drift between this machine and EVE's SSO servers.
        // Without leeway, firebase/php-jwt rejects tokens whose `iat`/`nbf` sit a
        // few seconds in the future relative to the local clock (BeforeValidException).
        JWT::$leeway = 60;

        $eveUser = Socialite::driver('eveonline')->stateless()->user();

        // The decoded JWT is the source of truth for identity.
        $jwt = $eveUser->getRaw();
        $characterId = (int) substr($jwt['sub'], strrpos($jwt['sub'], ':') + 1);

        $character = Character::updateOrCreate(
            ['character_id' => $characterId],
            [
                'name' => $jwt['name'],
                'owner_hash' => $jwt['owner'] ?? null,
                'access_token' => $eveUser->token,
                'refresh_token' => $eveUser->refreshToken,
                'token_expires_at' => Carbon::now()->addSeconds((int) ($eveUser->expiresIn ?? 1200)),
            ]
        );

        Session::put('active_character_id', $character->character_id);

        return redirect('/')->with('status', "Logged in as {$character->name}.");
    }

    public function logout()
    {
        Session::forget('active_character_id');

        return redirect('/')->with('status', 'Logged out.');
    }
}
