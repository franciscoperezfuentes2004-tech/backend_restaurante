<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Menú
            [
                'nombre'      => 'Ver Categorías',
                'descripcion' => 'Acceso a la lista de categorías del menú',
                'modulo'      => 'Menú',
                'clave'       => 'ver_categorias',
            ],
            [
                'nombre'      => 'Gestionar Categorías',
                'descripcion' => 'Crear, editar y eliminar categorías',
                'modulo'      => 'Menú',
                'clave'       => 'gestionar_categorias',
            ],
            [
                'nombre'      => 'Ver Platillos',
                'descripcion' => 'Acceso al catálogo de platillos',
                'modulo'      => 'Menú',
                'clave'       => 'ver_platillos',
            ],
            [
                'nombre'      => 'Gestionar Platillos',
                'descripcion' => 'Crear, editar y eliminar platillos',
                'modulo'      => 'Menú',
                'clave'       => 'gestionar_platillos',
            ],
            [
                'nombre'      => 'Gestionar Extras',
                'descripcion' => 'Crear y editar extras del menú',
                'modulo'      => 'Menú',
                'clave'       => 'gestionar_extras',
            ],

            // Operaciones
            [
                'nombre'      => 'Ver Pedidos',
                'descripcion' => 'Acceso al historial de pedidos',
                'modulo'      => 'Operaciones',
                'clave'       => 'ver_pedidos',
            ],
            [
                'nombre'      => 'Ver Reservaciones',
                'descripcion' => 'Acceso a la lista de reservaciones',
                'modulo'      => 'Operaciones',
                'clave'       => 'ver_reservaciones',
            ],
            [
                'nombre'      => 'Gestionar Reservaciones',
                'descripcion' => 'Confirmar y cancelar reservaciones',
                'modulo'      => 'Operaciones',
                'clave'       => 'gestionar_reservaciones',
            ],
            [
                'nombre'      => 'Ver Delivery',
                'descripcion' => 'Acceso a supervisión de entregas',
                'modulo'      => 'Operaciones',
                'clave'       => 'ver_delivery',
            ],

            // Marketing
            [
                'nombre'      => 'Gestionar Promociones',
                'descripcion' => 'Crear y editar promociones',
                'modulo'      => 'Marketing',
                'clave'       => 'gestionar_promociones',
            ],
            [
                'nombre'      => 'Ver Reseñas',
                'descripcion' => 'Acceso a reseñas de clientes',
                'modulo'      => 'Marketing',
                'clave'       => 'ver_resenas',
            ],
            [
                'nombre'      => 'Responder Reseñas',
                'descripcion' => 'Publicar respuestas a reseñas',
                'modulo'      => 'Marketing',
                'clave'       => 'responder_resenas',
            ],

            // Inventario
            [
                'nombre'      => 'Ver Inventario',
                'descripcion' => 'Ver ingredientes, stock y proveedores',
                'modulo'      => 'Inventario',
                'clave'       => 'ver_inventario',
            ],
            [
                'nombre'      => 'Gestionar Inventario',
                'descripcion' => 'Editar stock e ingredientes',
                'modulo'      => 'Inventario',
                'clave'       => 'gestionar_inventario',
            ],
            [
                'nombre'      => 'Gestionar Proveedores',
                'descripcion' => 'Crear y editar proveedores',
                'modulo'      => 'Inventario',
                'clave'       => 'gestionar_proveedores',
            ],

            // Administración
            [
                'nombre'      => 'Ver Dashboard',
                'descripcion' => 'Acceso al panel de métricas generales',
                'modulo'      => 'Administración',
                'clave'       => 'ver_dashboard',
            ],
            [
                'nombre'      => 'Ver Reportes',
                'descripcion' => 'Exportar reportes de ventas',
                'modulo'      => 'Administración',
                'clave'       => 'ver_reportes',
            ],
            [
                'nombre'      => 'Ver Bitácora',
                'descripcion' => 'Acceso al historial de actividad',
                'modulo'      => 'Administración',
                'clave'       => 'ver_bitacora',
            ],
            [
                'nombre'      => 'Configuración',
                'descripcion' => 'Modificar datos y ajustes del restaurante',
                'modulo'      => 'Administración',
                'clave'       => 'configuracion',
            ],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(
                ['clave' => $p['clave']],
                [
                    'nombre'      => $p['nombre'],
                    'descripcion' => $p['descripcion'],
                    'modulo'      => $p['modulo'],
                ]
            );
        }

        // Base Role Permissions setup
        $allPermIds = Permission::pluck('id', 'clave')->toArray();

        $defaultRolePermissions = [
            'super_admin' => array_keys($allPermIds),
            'admin'       => array_keys($allPermIds),
            'gerente'     => [
                'ver_categorias','gestionar_categorias','ver_platillos','gestionar_platillos','gestionar_extras',
                'ver_pedidos','ver_reservaciones','gestionar_reservaciones','ver_delivery',
                'gestionar_promociones','ver_resenas','responder_resenas',
                'ver_inventario','gestionar_inventario','ver_dashboard','ver_reportes'
            ],
            'manager'     => [
                'ver_categorias','gestionar_categorias','ver_platillos','gestionar_platillos','gestionar_extras',
                'ver_pedidos','ver_reservaciones','gestionar_reservaciones','ver_delivery',
                'gestionar_promociones','ver_resenas','responder_resenas',
                'ver_inventario','gestionar_inventario','ver_dashboard','ver_reportes'
            ],
            'mesero'      => ['ver_categorias', 'ver_platillos', 'ver_pedidos', 'ver_reservaciones', 'ver_delivery'],
            'waiter'      => ['ver_categorias', 'ver_platillos', 'ver_pedidos', 'ver_reservaciones', 'ver_delivery'],
            'cajero'      => ['ver_categorias', 'ver_platillos', 'ver_pedidos', 'ver_reservaciones', 'ver_delivery'],
            'cocina'      => ['ver_platillos', 'ver_pedidos', 'ver_inventario'],
            'kitchen'     => ['ver_platillos', 'ver_pedidos', 'ver_inventario'],
            'repartidor'  => ['ver_pedidos', 'ver_delivery'],
            'driver'      => ['ver_pedidos', 'ver_delivery'],
        ];

        foreach ($defaultRolePermissions as $role => $claveList) {
            foreach ($allPermIds as $clave => $permId) {
                $isActive = in_array($clave, $claveList, true);
                RolePermission::updateOrCreate(
                    ['role' => $role, 'permission_id' => $permId],
                    ['activo' => $isActive]
                );
            }
        }
    }
}
