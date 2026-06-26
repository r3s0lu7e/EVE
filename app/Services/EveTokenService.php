<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EveTokenService
{
    private const TOKEN_URL = 'https://login.eveonline.com/v2/oauth/token';

    /**
     * Return a valid access token for the character, refreshing it first if needed.
     */
    public function validAccessToken(Character $character): string
    {
        if ($character->tokenIsExpiring()) {
            $this->refresh($character);
        }

        return $character->access_token;
    }

    /**
     * Exchange the stored refresh token for a fresh access token and persist it.
     * EVE may rotate the refresh token, so we store whatever comes back.
     */
    public function refresh(Character $character): void
    {
        if (empty($character->refresh_token)) {
            throw new RuntimeException("Character {$character->character_id} has no refresh token; re-login required.");
        }

        $response = Http::asForm()
            ->withBasicAuth(config('services.eveonline.client_id'), config('services.eveonline.client_secret'))
            ->withHeaders(['Host' => 'login.eveonline.com'])
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $character->refresh_token,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Token refresh failed for character {$character->character_id}: ".$response->status().' '.$response->body()
            );
        }

        $data = $response->json();

        $character->forceFill([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $character->refresh_token,
            'token_expires_at' => Carbon::now()->addSeconds((int) ($data['expires_in'] ?? 1200)),
        ])->save();
    }
}
