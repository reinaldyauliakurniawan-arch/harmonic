<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Seat;
use App\Models\User;
use App\Services\Onboarding;
use App\Services\Tenant;
use Illuminate\Database\Seeder;

/** Local demo data: a Super Admin and one organization with a small seat tree. Never run against production. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create(['name' => 'Sam Owner', 'email' => 'owner@example.com', 'password' => 'harmonic123']);
        $admin->forceFill(['is_super_admin' => true])->save();

        [$org, $m, $root] = app(Onboarding::class)->createOrganization('Northwind Studio', 'active', 'self', $admin);
        $org->forceFill(['quarter_start_date' => now()->startOfQuarter()->toDateString()])->save();
        app(Tenant::class)->set($org, $m);

        $people = [];
        foreach (['Lena Park' => 'lena@example.com', 'Rio Santos' => 'rio@example.com', 'Oli Brandt' => 'oli@example.com'] as $name => $email) {
            $u = User::create(['name' => $name, 'email' => $email, 'password' => 'harmonic123']);
            $p = new Membership;
            $p->forceFill(['user_id' => $u->id, 'status' => 'active', 'joined_at' => now()])->save();
            $people[] = $p;
        }
        $seat = function (string $title, Seat $parent, ?Membership $holder, array $resp) {
            $s = new Seat(['title' => $title, 'responsibilities' => $resp]);
            $s->forceFill(['parent_id' => $parent->id, 'holder_id' => $holder?->id])->save();

            return $s;
        };
        $root->forceFill(['responsibilities' => ['Set direction', 'Lead the leadership team', 'Hire and develop leaders']])->save();
        $sales = $seat('Sales Lead', $root, $people[0], ['Hit the revenue plan', 'Run the sales team', 'Own the pipeline']);
        $seat('Account Executive', $sales, $people[1], ['Run demos', 'Close new accounts', 'Keep the CRM current']);
        $seat('Operations Lead', $root, $people[2], ['Deliver projects on time', 'Own delivery quality', 'Run the ops team']);
        app(Tenant::class)->forget();
    }
}
