<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class FirstAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder crea el primer usuario administrador del sistema.
    * IMPORTANTE: Usa un email demo o tu email de prueba en Microsoft 365
     */
    public function run(): void
    {
        // Obtener el rol de Admin
        $adminRole = Role::where('name', 'Admin')->first();

        if (!$adminRole) {
            $this->command->error('Error: El rol Admin no existe. Ejecuta primero RoleSeeder');
            return;
        }

        // Emails de administradores a crear
        $admins = [
            [
                'name' => 'Usuario Demo',
                'email' => 'admin@demo.example.org',
            ],
        ];

        foreach ($admins as $adminData) {
            // Verificar si el admin ya existe
            $existingAdmin = User::where('email', $adminData['email'])->first();

            if ($existingAdmin) {
                $this->command->warn("El usuario admin '{$adminData['name']}' ({$adminData['email']}) ya existe");
                
                // Verificar si tiene el rol de Admin
                if (!$existingAdmin->hasRole('Admin')) {
                    $existingAdmin->roles()->syncWithoutDetaching([$adminRole->id]);
                    $this->command->info("   Rol Admin asignado a {$adminData['email']}");
                }
                
                // Asegurar que está activo
                if (!$existingAdmin->is_active) {
                    $existingAdmin->update(['is_active' => true]);
                    $this->command->info("   Usuario {$adminData['email']} activado");
                }
                
                continue;
            }

            // Crear el admin
            $admin = User::create([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'is_active' => true,
                'password' => Hash::make('password'), // Password temporal (no se usará con Microsoft)
                'microsoft_id' => null, // Se asignará cuando inicie sesión por primera vez
                'created_by' => null // Es el primer usuario
            ]);

            // Asignar el rol de Admin (relación many-to-many)
            $admin->roles()->attach($adminRole->id);

            $this->command->info(" Usuario Admin creado exitosamente:");
            $this->command->info("  Nombre: {$admin->name}");
            $this->command->info("  Email: {$admin->email}");
            $this->command->info("  Rol: Admin");
        }

        $this->command->warn('');
        $this->command->warn(' IMPORTANTE: Estos usuarios pueden iniciar sesión con Microsoft 365');
    }
}

