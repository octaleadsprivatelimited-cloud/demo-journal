<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\{User,Role};
if (!app()->environment('production')) throw new RuntimeException('Production only');
$password = trim(file_get_contents('/run/journal-admin-password'));
DB::transaction(function () use($password) {
    // A new deployment must not reuse localhost sessions or queued messages.
    foreach(['sessions','password_reset_tokens','personal_access_tokens','jobs','failed_jobs'] as $table) DB::table($table)->delete();
    foreach(User::where('is_local_admin_bypass',true)->get() as $user) {
        $user->roles()->detach();
        $user->forceFill(['is_active'=>false,'email_verified_at'=>null])->save();
    }
    $admin=User::firstOrNew(['email'=>'info@octaleads.com']);
    $admin->forceFill(['name'=>'Journal Administrator','password'=>Hash::make($password),'status'=>'active','is_active'=>true,'email_verified_at'=>now(),'is_local_admin_bypass'=>false])->save();
    $admin->roles()->syncWithoutDetaching([Role::where('slug','super-admin')->firstOrFail()->id]);
});
echo 'Production admin created; local sessions and bypass access removed.'.PHP_EOL;
