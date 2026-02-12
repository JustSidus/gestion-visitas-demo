<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Administrador del sistema con acceso total, incluyendo gestión de usuarios, roles y configuración.'
            ],
            [
                'name' => 'Asist_adm',
                'description' => 'Asistente administrativo con acceso general a todas las funcionalidades del sistema para visitas NO misionales.'
            ],
            [
                'name' => 'Guardia',
                'description' => 'Guardia de seguridad que puede ver visitas activas, darles salida (4pm-11:59pm), validar QR y registrar placas.'
            ],
            [
                'name' => 'aux_ugc',
                'description' => 'Auxiliar UGC que gestiona únicamente visitas activas misionales (puede ver y cerrar, no puede crear visitas).'
            ]
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }

        $this->command->info(' Roles creados exitosamente:');
        $this->command->info('   - Admin: Acceso total al sistema');
        $this->command->info('   - Asist_adm: Gestión de visitas NO misionales');
        $this->command->info('   - Guardia: Control de acceso y salida');
        $this->command->info('   - aux_ugc: Gestión de visitas misionales');
    }
}
