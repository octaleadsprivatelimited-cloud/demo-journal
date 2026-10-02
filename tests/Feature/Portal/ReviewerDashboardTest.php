<?php

namespace Tests\Feature\Portal;

use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_profile_and_availability_save_without_changing_access(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', 'reviewer')->firstOrFail());
        $this->actingAs($user)->get('/reviewer/profile')->assertOk()->assertSee('Areas of expertise');
        $this->get('/reviewer/settings')->assertOk()->assertSee('Save availability');
        $this->put('/reviewer/profile', ['name' => 'Reviewer Example', 'expertise' => 'Soil science', 'is_active' => false])->assertSessionHasNoErrors();
        $this->put('/reviewer/settings', ['available' => '0'])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Reviewer Example', $user->name);
        $this->assertSame('Soil science', $user->reviewer_profile['expertise']);
        $this->assertFalse($user->reviewer_profile['available']);
        $this->assertTrue($user->isActive());
        $this->put('/reviewer/profile', ['name' => 'Updated'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->reviewer_profile['available']);
    }

    public function test_reviewer_can_filter_only_their_assignments_and_view_closed_states(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', 'reviewer')->firstOrFail());
        $assigned = Review::factory()->create(['reviewer_id' => $user->id, 'status' => 'assigned', 'completed_at' => null]);
        $declined = Review::factory()->create(['reviewer_id' => $user->id, 'status' => 'declined', 'completed_at' => null]);
        $other = Review::factory()->create(['status' => 'assigned', 'completed_at' => null]);
        $this->actingAs($user)->get('/reviewer/dashboard?status=assigned')->assertOk()
            ->assertSee($assigned->article->title)->assertDontSee($declined->article->title)
            ->assertDontSee($other->article->title)->assertSee('Completed reports')->assertSee('Apply filters');
        $this->get('/reviewer/reviews/'.$declined->id)->assertOk()->assertSee('No review action is required.')
            ->assertDontSee('Submit final review')->assertDontSee('Download manuscript');
        $this->get('/reviewer/reviews/'.$other->id)->assertForbidden();
        $this->get('/reviewer/dashboard?q=definitely-no-matching-manuscript')->assertOk()->assertSee('No matching assignments');
    }
}
