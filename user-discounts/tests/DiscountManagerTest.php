<?php

namespace UserDiscounts\Tests;

use Orchestra\Testbench\TestCase;
use UserDiscounts\UserDiscountsServiceProvider;
use UserDiscounts\Services\DiscountManager;
use UserDiscounts\Models\Discount;
use UserDiscounts\Models\UserDiscount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DiscountManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            UserDiscountsServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Create a user
        User::factory()->create(['id' => 1]);
    }

    /** @test */
    public function testAssignAndRevokeDiscount()
    {
        $user = User::find(1);
        $discount = Discount::create([
            'name' => 'Test Discount',
            'type' => 'fixed',
            'value' => 10.00,
            'active' => true,
        ]);

        $manager = $this->app->make(DiscountManager::class);

        $userDiscount = $manager->assign($user, $discount);
        $this->assertDatabaseHas('user_discounts', [
            'user_id' => $user->id,
            'discount_id' => $discount->id,
            'revoked_at' => null,
        ]);

        $revoked = $manager->revoke($user, $discount);
        $this->assertTrue($revoked);

        $this->assertDatabaseMissing('user_discounts', [
            'user_id' => $user->id,
            'discount_id' => $discount->id,
            'revoked_at' => null,
        ]);
    }

    /** @test */
    public function testApplyDiscountRespectsUsageCap()
    {
        $user = User::find(1);
        $discount = Discount::create([
            'name' => 'Limited Use Discount',
            'type' => 'fixed',
            'value' => 10.00,
            'active' => true,
            'usage_cap' => 2,
        ]);

        $manager = $this->app->make(DiscountManager::class);
        $manager->assign($user, $discount);

        $amount = 100;

        // Apply discount first time
        $final = $manager->apply($user, $amount);
        $this->assertEquals(90, $final);

        // Apply discount second time
        $final = $manager->apply($user, $amount);
        $this->assertEquals(90, $final);

        // Usage cap reached, discount no longer applied
        $final = $manager->apply($user, $amount);
        $this->assertEquals(100, $final);
    }

    /** @test */
    public function testApplyDiscountRespectsMaxPercentageCapAndRounding()
    {
        $user = User::find(1);

        $manager = $this->app->make(DiscountManager::class);

        // Override config for this test
        $managerReflection = new \ReflectionClass($manager);
        $configProp = $managerReflection->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue($manager, [
            'stacking_order' => ['percentage', 'fixed'],
            'max_percentage_cap' => 25,
            'rounding' => 'down',
        ]);

        $discount1 = Discount::create([
            'name' => '10% Off',
            'type' => 'percentage',
            'value' => 10.0,
            'active' => true,
        ]);

        $discount2 = Discount::create([
            'name' => '20% Off',
            'type' => 'percentage',
            'value' => 20.0,
            'active' => true,
        ]);

        $manager->assign($user, $discount1);
        $manager->assign($user, $discount2);

        $amount = 100;

        $final = $manager->apply($user, $amount);
        
        $this->assertEquals(75.00, $final);
    }

    protected function getEnvironmentSetUp($app)
{
    $app['config']->set('database.default', 'mysql');
    $app['config']->set('database.connections.mysql', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'userdiscounttest',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ]);
}

}