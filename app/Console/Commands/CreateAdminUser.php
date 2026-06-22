<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {email? : Admin email address}
                            {--name= : Display name}
                            {--password= : Password (will prompt securely if omitted)}';

    protected $description = 'Create or update an admin user for the dashboard login';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Admin email');
        $name = $this->option('name') ?: $this->ask('Display name', 'Admin');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated') . " admin user: {$email}");

        return self::SUCCESS;
    }
}
