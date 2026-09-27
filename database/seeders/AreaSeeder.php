<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Mesa;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $area = Area::where('nombre', 'Terraza')
            ->orWhere('name', 'Terraza')
            ->first();

        if (!$area) {
            $area = Area::create([
                'nombre'             => 'Terraza',
                'name'               => 'Terraza',
                'capacidad_personas' => 20,
                'numero_mesas'       => 5,
                'tables_count'       => 5,
                'capacity_per_table' => 4,
                'capacity'           => 20,
                'is_active'          => true,
                'active'             => true,
            ]);
        } else {
            $area->update([
                'nombre'             => 'Terraza',
                'name'               => 'Terraza',
                'capacidad_personas' => 20,
                'numero_mesas'       => 5,
                'tables_count'       => 5,
                'capacity_per_table' => 4,
                'capacity'           => 20,
                'is_active'          => true,
                'active'             => true,
            ]);
        }

        // Seed 5 tables for Terraza
        for ($i = 1; $i <= 5; $i++) {
            Mesa::firstOrCreate(
                ['area_id' => $area->id, 'numero_mesa' => $i],
                [
                    'capacidad' => 4,
                    'is_active' => true,
                ]
            );
        }
    }
}
