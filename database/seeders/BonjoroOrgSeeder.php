<?php

namespace Database\Seeders;

use App\Models\Billing\BillingProvider;
use App\Models\Client;
use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BonjoroOrgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'mitch+bonjoro@flindev.com';
        $i = DB::table('users')
            ->where('email', $email)
            ->pluck('id')
            ->first();
        if ($i) {
            DB::table('mgmt_memberships')
                ->where(['user_id' => $i])
                ->delete();
            DB::table('users')
                ->where(['id' => $i])
                ->delete();
        }

        $user = User::factory()
            ->create([
                'email' => $email,
                'password' => bcrypt('mitchell'),
            ]);

        /* @var Organization $org */
        $org = Organization::factory()
            ->create(['name' => 'bonjoro']);

        $org->users()->attach($user->id, ['role' => 'owner']);

        $client = Client::factory()
            ->for($org)
            ->web()
            ->create([
                //                'ulid' => '01HN9JKJB3S24SNNTKB8FCAEX1',
                //                'secret' => 'jVg40BOI46SQnfQTL7AaSocBsnzqhr2y',
            ]);

        BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        //        ClientBootstrap::factory($org, $client)
        //            ->bonjoro();
    }
}
