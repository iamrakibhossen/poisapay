<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\P2pPaymentMethod;
use App\Models\P2pUserPaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);

    $this->method = P2pPaymentMethod::create([
        'key' => 'qa_rail', 'name' => 'QA Rail', 'type' => 'mobile', 'country' => 'BD', 'is_active' => true, 'sort' => 0,
        'fields' => [['key' => 'account_number', 'label' => 'Account number', 'required' => true]],
    ]);
});

it('renders the list and links each method to its detail page', function () {
    actingAs($this->admin, 'admin')->get(route('admin.p2p-payment-methods'))
        ->assertOk()
        ->assertSee('QA Rail')
        ->assertSee(route('admin.p2p-payment-methods.show', $this->method), escape: false);
});

it('renders the payment-method detail page with config and fields', function () {
    actingAs($this->admin, 'admin')->get(route('admin.p2p-payment-methods.show', $this->method))
        ->assertOk()
        ->assertSee('QA Rail')
        ->assertSee('qa_rail')
        ->assertSee('Account number');
});

it('lists user accounts on the rail without leaking encrypted details', function () {
    $user = User::factory()->create(['name' => 'Jane Payer']);
    P2pUserPaymentMethod::create([
        'user_id' => $user->id,
        'payment_method_id' => $this->method->id,
        'label' => 'My bKash',
        'account' => ['account_number' => '017SECRET1234'],
        'is_active' => true,
    ]);

    actingAs($this->admin, 'admin')->get(route('admin.p2p-payment-methods.show', $this->method))
        ->assertOk()
        ->assertSee('Jane Payer')
        ->assertSee('My bKash')
        ->assertDontSee('017SECRET1234'); // encrypted account contents are never rendered
});

it('deleting a method from the detail page redirects to the list', function () {
    actingAs($this->admin, 'admin')
        ->delete(route('admin.p2p-payment-methods.delete', $this->method))
        ->assertRedirect(route('admin.p2p-payment-methods'))
        ->assertSessionHas('success');

    expect(P2pPaymentMethod::find($this->method->id))->toBeNull();
});

it('refuses to delete a method that still has user accounts', function () {
    $user = User::factory()->create();
    P2pUserPaymentMethod::create([
        'user_id' => $user->id,
        'payment_method_id' => $this->method->id,
        'account' => ['account_number' => '017000'],
        'is_active' => true,
    ]);

    actingAs($this->admin, 'admin')
        ->delete(route('admin.p2p-payment-methods.delete', $this->method))
        ->assertSessionHas('error');

    expect(P2pPaymentMethod::find($this->method->id))->not->toBeNull();
});

it('requires the manage-p2p ability', function () {
    $plain = Admin::create([
        'name' => 'Nobody', 'email' => 'no@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($plain, 'admin')->get(route('admin.p2p-payment-methods.show', $this->method))
        ->assertForbidden();
});
