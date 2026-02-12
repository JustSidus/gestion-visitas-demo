<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Obtener ID del rol Solicitante
        $solicitanteRoleId = DB::table('roles')->where('name', 'Solicitante')->value('id');
        
        if ($solicitanteRoleId) {
            // 2. Obtener IDs de usuarios con rol Solicitante (desde tabla pivote)
            $userIds = DB::table('role_user')
                ->where('role_id', $solicitanteRoleId)
                ->pluck('user_id')
                ->toArray();
            
            // 3. Desactivar esos usuarios
            if (!empty($userIds)) {
                DB::table('users')
                    ->whereIn('id', $userIds)
                    ->update(['is_active' => false]);
            }
            
            // 4. Eliminar registros de la tabla pivote
            DB::table('role_user')
                ->where('role_id', $solicitanteRoleId)
                ->delete();
            
            // 5. Eliminar el rol Solicitante
            DB::table('roles')->where('name', 'Solicitante')->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recrear rol Solicitante
        DB::table('roles')->insert([
            'name' => 'Solicitante',
            'description' => 'Puede crear solicitudes de visitas (rol deprecado)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Nota: No reactivamos usuarios automáticamente, eso debe hacerse manualmente si es necesario
    }
};
