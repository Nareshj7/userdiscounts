# Laravel User Discounts

Reusable Laravel package that manages deterministic user-level discount stacking with audit trails and concurrency safeguards.

## Installation

```bash
composer require naresh/user-discount
```

Publish the configuration and migrations when installing into a Laravel app:

```bash
php artisan vendor:publish --provider="Naresh\UserDiscounts\Providers\UserDiscountsServiceProvider" --tag=user-discounts-config
php artisan vendor:publish --provider="Naresh\UserDiscounts\Providers\UserDiscountsServiceProvider" --tag=user-discounts-migrations
```

## Configuration

`config/user-discounts.php` exposes sensible defaults:

- `models.user` – override the authenticatable model if different from the default guard.
- `stacking.order` / `stacking.direction` – deterministic sort (priority, assigned_at, usage_count).
- `caps.max_percentage` – global percentage ceiling across stacked discounts.
- `caps.rounding` / `caps.precision` – rounding strategy (`round`, `floor`, `ceil`).
- `concurrency.lock_timeout` – future hook for database locking strategies.

## Usage

Resolve the manager via the container or use the facade:

```php
use Naresh\UserDiscounts\Contracts\DiscountManager;
use Naresh\UserDiscounts\Facades\UserDiscounts;

$manager = app(DiscountManager::class);

$manager->assign($user, 'SAVE10');
$eligible = $manager->eligibleFor($user, ['amount' => 120_00]);
$total = $manager->apply($user, 120.00, ['order_id' => 987]);

UserDiscounts::revoke($user, 'SAVE10');
```

Events `DiscountAssigned`, `DiscountRevoked`, and `DiscountApplied` are dispatched with the user, discount, and pivot context for auditing or integrations.

## Testing

```bash
composer test
```

The included unit suite verifies deterministic stacking, usage caps, and eligibility logic using Orchestra Testbench.