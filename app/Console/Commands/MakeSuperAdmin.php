<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin {email}';

    protected $description = 'Flag an existing account as Super Admin';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('No account uses this email.');

            return self::FAILURE;
        }
        $user->forceFill(['is_super_admin' => true])->save();
        $this->info("{$user->email} is now a Super Admin.");

        return self::SUCCESS;
    }
}
