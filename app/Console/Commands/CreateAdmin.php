<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'emc:create-admin {username?} {password?} {name?}';

    protected $description = 'Create or reset an EMC administrator account';

    public function handle(): int
    {
        $username = strtolower(trim((string) ($this->argument('username') ?: getenv('EMC_ADMIN_USER'))));
        $password = (string) ($this->argument('password') ?: getenv('EMC_ADMIN_PASSWORD'));
        $name = trim((string) ($this->argument('name') ?: getenv('EMC_ADMIN_NAME') ?: 'EMC Administrator'));
        if (! preg_match('/^[a-z0-9_.-]{3,50}$/', $username) || strlen($password) < 10 || strlen($password) > 72 || mb_strlen($name) < 2) {
            $this->error('Provide a valid username, a 10-72 character password, and a display name.');

            return self::FAILURE;
        }
        Admin::updateOrCreate(['username' => $username], ['password_hash' => Hash::make($password), 'display_name' => $name, 'is_active' => true]);
        $this->info("Administrator account created or updated: {$username}");

        return self::SUCCESS;
    }
}
