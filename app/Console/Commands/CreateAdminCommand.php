<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'user:create-admin {--email=} {--name=} {--password=}';

    protected $description = 'Create or promote a super administrator without embedding credentials in source control';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: ($this->input->isInteractive() ? $this->ask('Email address') : ''));
        $name = (string) ($this->option('name') ?: ($this->input->isInteractive() ? $this->ask('Display name') : ''));
        $password = (string) ($this->option('password') ?: ($this->input->isInteractive() ? $this->secret('Password (12+ characters)') : ''));

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(9)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status' => 'active',
            'is_active' => true,
        ])->save();

        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Full platform access', 'is_system' => true],
        );
        $user->roles()->syncWithoutDetaching([$role->getKey() => ['assigned_at' => now()]]);

        $this->components->info("Super administrator {$user->email} is ready.");

        return self::SUCCESS;
    }
}
