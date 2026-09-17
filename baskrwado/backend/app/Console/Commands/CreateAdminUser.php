<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'baskrwado:admin {email} {--name=Owner} {--role=owner} {--password=}';
    protected $description = 'Create or update a BasKarwaDo admin user';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if (strlen($password) < 10) {
            $this->error('Use a password of at least 10 characters.');
            return self::FAILURE;
        }

        $role = (string) $this->option('role');
        if (!in_array($role, ['owner', 'admin', 'agent', 'reviewer'], true)) {
            $this->error('Role must be owner, admin, agent or reviewer.');
            return self::FAILURE;
        }

        $user = AdminUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) $this->option('name'),
                'password' => Hash::make($password),
                'role' => $role,
                'active' => true,
            ],
        );

        $this->info("Admin ready: {$user->email} ({$user->role})");
        return self::SUCCESS;
    }
}
