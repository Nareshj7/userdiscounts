<?php

namespace Naresh\UserDiscounts\Tests\Unit;

use Naresh\UserDiscounts\Contracts\DiscountManager;
use Naresh\UserDiscounts\Models\Discount;
use Naresh\UserDiscounts\Tests\Support\TestUser;
use Naresh\UserDiscounts\Tests\TestCase;
use Illuminate\Support\Str;

class DiscountManagerTest extends TestCase
{
    public function test_apply_calculates_stacked_discounts_respecting_caps(): void
    {
        /** @var DiscountManager $manager */
        $manager = $this->app->make(DiscountManager::class);
        $user = $this->createUser();

        $percent = Discount::query()->create([
            'code' => 'SAVE20',
            'name' => 'Twenty Percent',
            'type' => 'percentage',
            'value' => 20,
            'priority' => 20,
            'is_stackable' => true,
            'is_active' => true,
        ]);

        $fixed = Discount::query()->create([
            'code' => 'FLAT10',
            'name' => 'Ten Off',
            'type' => 'fixed',
            'value' => 10,
            'priority' => 10,
            'is_stackable' => false,
            'is_active' => true,
        ]);

        $manager->assign($user, $percent->code);
        $manager->assign($user, $fixed->code);

        $final = $manager->apply($user, 200);

        $this->assertSame(150.0, $final);

        $this->assertDatabaseHas('user_discounts', [
            'user_id' => $user->getAuthIdentifier(),
            'discount_id' => $percent->id,
            'usage_count' => 1,
        ]);

        $this->assertDatabaseHas('discount_audits', [
            'discount_id' => $percent->id,
            'action' => 'applied',
        ]);

        $this->assertDatabaseHas('discount_audits', [
            'discount_id' => $fixed->id,
            'action' => 'applied',
        ]);
    }

    public function test_usage_cap_prevents_additional_applications(): void
    {
        config()->set('user-discounts.caps.max_percentage', 100);

        /** @var DiscountManager $manager */
        $manager = $this->app->make(DiscountManager::class);
        $user = $this->createUser();

        $discount = Discount::query()->create([
            'code' => 'ONEUSE',
            'name' => 'One Time',
            'type' => 'fixed',
            'value' => 15,
            'priority' => 5,
            'is_stackable' => true,
            'is_active' => true,
            'max_uses_per_user' => 1,
        ]);

        $manager->assign($user, $discount->code);

        $first = $manager->apply($user, 100);
        $second = $manager->apply($user, 80);

        $this->assertSame(85.0, $first);
        $this->assertSame(80.0, $second);

        $this->assertDatabaseHas('user_discounts', [
            'user_id' => $user->getAuthIdentifier(),
            'discount_id' => $discount->id,
            'usage_count' => 1,
        ]);
    }

    public function test_eligible_for_excludes_expired_and_revoked_discounts(): void
    {
        /** @var DiscountManager $manager */
        $manager = $this->app->make(DiscountManager::class);
        $user = $this->createUser();

        $active = Discount::query()->create([
            'code' => 'ACTIVE',
            'name' => 'Active',
            'type' => 'fixed',
            'value' => 5,
            'priority' => 1,
            'is_active' => true,
        ]);

        $expired = Discount::query()->create([
            'code' => 'OLD',
            'name' => 'Expired',
            'type' => 'fixed',
            'value' => 5,
            'priority' => 1,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $inactive = Discount::query()->create([
            'code' => 'INACTIVE',
            'name' => 'Inactive',
            'type' => 'fixed',
            'value' => 5,
            'priority' => 1,
            'is_active' => true,
        ]);

        $manager->assign($user, $active->code);
        $manager->assign($user, $expired->code);
        $manager->assign($user, $inactive->code);

        $expired->update(['expires_at' => now()->subDay()]);
        $inactive->update(['is_active' => false]);
        $manager->revoke($user, $inactive->code);

        $eligible = $manager->eligibleFor($user, ['amount' => 50]);

        $this->assertCount(1, $eligible);
        $this->assertSame('ACTIVE', $eligible->first()->code);
    }

    private function createUser(): TestUser
    {
        return TestUser::query()->create([
            'name' => 'User ' . Str::uuid(),
            'email' => Str::uuid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}