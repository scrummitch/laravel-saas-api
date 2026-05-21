<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-password';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->ask('What is the email address?');

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            $this->error("User with email $email not found");

            return;
        }

        $password = $this->secret('What is the new password?');

        $this->info("The password is: $password");

        $user->password = bcrypt($password);
        $user->save();

        $this->info("Password reset for $email");
    }
}
