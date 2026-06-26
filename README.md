# EVE Trade Profit Tracker

A local web app that imports your EVE Online wallet transactions via ESI and tracks
**realized trading profit** — by product, by day, by location, by character — with
filtering, charts, and CSV export. Profit is computed with **FIFO cost-basis matching**.

> ESI only retains ~30 days of wallet history. This app permanently archives every
> transaction to a local SQLite database on each sync, so your profit history grows
> over time. **Sync regularly so you don't lose history.**

## Stack

Laravel 12 · Livewire · SQLite · Laravel Socialite (EVE SSO) · ApexCharts.

## Setup

1. **Register an EVE application** at <https://developers.eveonline.com/>:
   - Callback URL: `http://localhost:8000/auth/eve/callback`
   - Scopes: `publicData`, `esi-wallet.read_character_wallet.v1`,
     `esi-universe.read_structures.v1`, `esi-markets.read_character_orders.v1`

2. **Configure** `.env` (already created by Composer; fill in the EVE values):
   ```
   EVE_CLIENT_ID=...
   EVE_CLIENT_SECRET=...
   EVE_REDIRECT_URI=http://localhost:8000/auth/eve/callback
   ```

3. **Migrate** (already done on install, but to reset): `php artisan migrate`

4. **Run**: `php artisan serve` then open <http://localhost:8000>.

5. **Log in with EVE**, then click **Sync now** (or run `php artisan eve:sync`).

### Preview without an EVE login

```
php artisan db:seed --class=DemoSeeder
```

Generates 26 days of fake trades so you can explore the dashboard immediately.

## Syncing

- Manual: `php artisan eve:sync` (or `--character=<id>` for one character), or the
  **Sync now** button in the UI.
- Scheduled hourly via `Schedule::command('eve:sync')->hourly()` in
  `routes/console.php`. To run the scheduler locally: `php artisan schedule:work`.

## How profit is calculated

- **FIFO** (`app/Services/CostBasis/FifoStrategy.php`): each sale consumes the oldest
  unsold buy lots. Swap the cost-basis method by rebinding `CostBasisStrategy` in
  `AppServiceProvider`.
- **Fees** (`FeeAllocator.php`): sales tax + broker fees from the wallet journal are
  subtracted to get **net profit**. Sales tax is linked to the specific sale via the
  journal `context_id` when available; otherwise both fees are spread proportionally to
  sell value. *Per-trade fee attribution is approximate, but the aggregate net profit
  is exact and reconciles with your wallet.*
- **Unmatched sells**: items sold whose buys predate the local archive have no cost
  basis. They're flagged `unmatched` and excluded from profit (shown as a count) rather
  than counted as pure profit. This improves automatically the longer you sync.

## Tests

```
php artisan test
```

Core correctness is covered by `tests/Unit/FifoStrategyTest.php`,
`tests/Unit/FeeAllocatorTest.php`, and `tests/Feature/ProfitEngineTest.php`.

## Notes

- The UI loads Tailwind + ApexCharts from CDNs so it runs without a frontend build.
  For a production build, swap the CDN tags in
  `resources/views/components/layouts/app.blade.php` for `@vite([...])`.
