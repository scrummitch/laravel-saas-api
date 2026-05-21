<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user';

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

        $password = strtolower(Str::random(32));

        $this->info("The password is: $password");

        $org = $this->ask('What is the organization name?');

        $this->info("Creating user $email for organization $org");

        $user = User::query()
            ->firstOrNew([
                'email' => $email,
            ]);

        $user->email = $email;
        $user->password = bcrypt($password);
        $user->save();

        // create the organization
        $organization = Organization::query()
            ->firstOrNew([
                'name' => $org,
            ]);
        $organization->save();

        // attach the user to the org
        $organization->users()->attach($user->id, [
            'role' => 'owner',
        ]);

        $this->info("User $email created for organization $org");

        Client::unguard();
        // create a client for the org
        $client = $organization->clients()->create([
            'type' => 'web',
            'name' => 'Web Client',
            'secret' => Str::random(32),
        ]);

        $this->table(['Client ID', 'Client Secret'], [[$client->getRouteKey(), $client->getSecretStr()]]);

    }
}
