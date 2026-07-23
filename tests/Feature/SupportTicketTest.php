<?php

declare(strict_types=1);

use App\Domain\Support\SupportTicketService;
use App\Enums\SupportTicketStatus;
use App\Models\Admin;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

function supportOperator(): Admin
{
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $admin = Admin::create(['name' => 'Sup', 'email' => 'sup@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->syncRoles(['super-admin']);

    return $admin;
}

it('lets a user open a ticket with an initial message', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('support.store'), [
        'subject' => 'Cannot withdraw', 'category' => 'withdrawal', 'priority' => 'high', 'body' => 'It fails every time.',
    ])->assertRedirect();

    $ticket = SupportTicket::where('user_id', $user->id)->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->messages()->count())->toBe(1)
        ->and($ticket->messages()->first()->is_staff)->toBeFalse();
});

it('renders the user support index and ticket pages', function () {
    $user = User::factory()->create();
    $ticket = app(SupportTicketService::class)->open($user, 'Help', 'general', 'normal', 'hello');

    actingAs($user)->get(route('support'))->assertOk()->assertSee('Help');
    actingAs($user)->get(route('support.show', $ticket->id))->assertOk()->assertSee('hello');
});

it('scopes ticket visibility to the owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ticket = app(SupportTicketService::class)->open($owner, 'Private', 'general', 'normal', 'secret');

    actingAs($other)->get(route('support.show', $ticket->id))->assertNotFound();
});

it('threads a staff reply, sets pending, and a user reply reopens it', function () {
    $user = User::factory()->create();
    $admin = supportOperator();
    $ticket = app(SupportTicketService::class)->open($user, 'Q', 'general', 'normal', 'first');

    actingAs($admin, 'admin')->post(route('admin.support.reply', $ticket->id), ['body' => 'staff answer'])->assertRedirect();
    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicketStatus::Pending)
        ->and(SupportMessage::where('ticket_id', $ticket->id)->where('is_staff', true)->count())->toBe(1)
        ->and($user->fresh()->notifications()->count())->toBeGreaterThanOrEqual(1); // user was notified

    actingAs($user)->post(route('support.reply', $ticket->id), ['body' => 'thanks, still broken'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open);
});

it('lets an operator triage and close tickets', function () {
    $user = User::factory()->create();
    $admin = supportOperator();
    $ticket = app(SupportTicketService::class)->open($user, 'Triage', 'general', 'normal', 'body');

    actingAs($admin, 'admin')->get(route('admin.support'))->assertOk()->assertSee('Triage');
    actingAs($admin, 'admin')->post(route('admin.support.status', $ticket->id), ['status' => 'closed'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Closed);

    // A closed ticket rejects further user replies.
    actingAs($user)->post(route('support.reply', $ticket->id), ['body' => 'more'])->assertSessionHasErrors('body');
});
