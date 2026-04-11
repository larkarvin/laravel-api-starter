<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create a new Filament admin user';

    public function handle(): int
    {
        $name = $this->ask('Name');

        $email = $this->askValidEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        $password = $this->secret('Password');

        if (blank($password)) {
            $this->error('Password cannot be empty.');

            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info('Admin created successfully!');
        $this->line("ID:    {$admin->id}");
        $this->line("Email: {$admin->email}");

        return self::SUCCESS;
    }

    private function askValidEmail(): ?string
    {
        $email = $this->ask('Email');

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'unique:admins,email']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return null;
        }

        return $email;
    }
}
