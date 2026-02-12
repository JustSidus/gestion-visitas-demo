<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed visit statuses first
        $this->call([
            VisitStatusSeeder::class,
        ]);

        // Seed roles (Admin, Asist_adm, Guardia, Solicitante)
        $this->call([
            RoleSeeder::class,
        ]);

        // Seed first admin user
        // IMPORTANTE: Edita FirstAdminSeeder.php y cambia el email antes de ejecutar
        $this->call([
            FirstAdminSeeder::class,
        ]);
    }
}
