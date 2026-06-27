<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Per-trade fee rates
    |--------------------------------------------------------------------------
    |
    | Broker fees and the market provider tax (SCC surcharge) are charged when an
    | order is *placed/relisted*, not when it fills — and the wallet journal gives
    | no way to tie a placement to the specific trade that later completes (the
    | broker_fee entries carry no context_id). Many of these fees are also for
    | orders that never sold. So realized-profit-per-trade cannot read them from
    | the journal; it estimates them at the rates below, applied to each completed
    | trade's own sale value.
    |
    | Sales tax (transaction_tax) is NOT estimated — it posts at the instant of
    | the sale and is linked exactly from the journal by timestamp.
    |
    | Set these to your character's effective rates (after Broker Relations /
    | Accounting skills and standings). The market provider tax only applies to
    | trades made at player-owned (Upwell) structures.
    */

    'broker_fee_rate' => (float) env('EVE_BROKER_FEE_RATE', 0.015),

    'market_provider_tax_rate' => (float) env('EVE_MARKET_PROVIDER_TAX_RATE', 0.0),
];
