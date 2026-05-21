<?php

namespace Database\Seeders;

use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedMitchAccount();
    }

    private function seedMitchAccount(): void
    {
        DB::table('mgmt_memberships')
            ->where('user_id', User::where('email', 'mitch@flindev.com')->first()?->id)
            ->delete();

        DB::table('users')
            ->where('email', 'mitch@flindev.com')
            ->delete();

        $user = User::factory()
            ->create([
                'email' => 'mitch@flindev.com',
                'first_name' => 'Mitch',
                'last_name' => 'Flindev',
                'password' => bcrypt('mitchell'),
            ]);

        /* @var Organization $org */
        $org = Organization::factory()
            ->create(['name' => 'flindev']);

        $org->users()->attach($user->id, ['role' => 'owner']);
    }

    public function seedOrbAccount() {}
}
