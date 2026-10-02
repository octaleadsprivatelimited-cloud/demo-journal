<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Http, Notification, Password};
use Tests\TestCase;

class EmailPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_changes_password_and_cannot_be_reused(): void
    {
        Http::fake(['*'=>Http::response('',200)]);
        Notification::fake();
        $user=User::factory()->create();
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
        $this->post(route('password.email'), ['email'=>$user->email])->assertSessionHas('success');
        $notification=Notification::sent($user, ResetPassword::class)->first();
        $this->assertNotNull($notification);
        $this->get(route('password.reset', ['token'=>$notification->token, 'email'=>$user->email]))->assertOk()->assertSee('Request a new reset link');
        $data=['email'=>$user->email,'token'=>$notification->token,'password'=>'Fresh!Recovery742','password_confirmation'=>'Fresh!Recovery742'];
        $this->post(route('password.store'),$data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($data['password'],$user->fresh()->password));
        $this->post(route('password.store'),$data)->assertSessionHasErrors('email');
    }

    public function test_expired_links_cannot_change_password(): void
    {
        Http::fake(['*'=>Http::response('',200)]);
        $user=User::factory()->create();
        $token=Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post(route('password.store'), ['email'=>$user->email,'token'=>$token,'password'=>'Fresh!Recovery742','password_confirmation'=>'Fresh!Recovery742'])->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('Fresh!Recovery742',$user->fresh()->password));
    }

    public function test_azure_transport_sends_html_and_secure_link_without_logging_mail(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '169.254.169.254/*'=>Http::sequence()->push(['access_token'=>'fake-identity-token'])->push([],403),
            'https://test.communication.azure.com/*'=>Http::response(['id'=>'test-send', 'status'=>'Running'],202),
        ]);
        $transport=new \App\Mail\AzureEmailTransport('https://test.communication.azure.com');
        $email=(new \Symfony\Component\Mime\Email)->from('sender@example.org')->to('reader@example.org')->subject('Reset password')->html('<a href="https://journal.example/reset-password/test-token">Reset</a>')->text('Reset your password');
        $transport->send($email);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/emails:send') && $request['recipients']['to'][0]['address']==='reader@example.org' && str_contains($request['content']['html'],'test-token') && $request->hasHeader('Authorization','Bearer fake-identity-token'));
        $this->expectException(\Symfony\Component\Mailer\Exception\TransportException::class);
        $transport->send($email);
    }
}
