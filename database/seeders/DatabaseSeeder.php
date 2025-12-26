<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesTableSeeder::class,
            PlansSeeder::class,
            CompanySeeder::class,
            SubscriptionsSeeder::class,
            ShiftSeeder::class,
            HolidaySeeder::class,
            LeavePolicySeeder::class,
            UsersSeeder::class,
            TimeEntriesSeeder::class,
            VacationExampleSeeder::class,
            AnnouncementSeeder::class,
        ]);
    }

}
