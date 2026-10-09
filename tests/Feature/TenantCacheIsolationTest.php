<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalA;
    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        // En tests, usamos el driver de Redis o array (ambos soportan tags)
        if (!Cache::supportsTags()) {
            config(['cache.default' => 'redis']);
        }

        $this->sucursalA = Sucursal::find(1) ?? Sucursal::create([
            'id'        => 1,
            'nombre'    => 'Sucursal Centro',
            'codigo'    => 'SUC-001',
            'is_active' => true,
        ]);

        $this->sucursalB = Sucursal::create([
            'nombre'    => 'Sucursal Playa',
            'codigo'    => 'SUC-002',
            'is_active' => true,
        ]);
    }

    public function test_tenant_cache_isolates_data_between_sucursales(): void
    {
        // Sucursal A
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        Cache::tenant()->put('catalogo_menu', 'Menu Exclusivo Sucursal A', 3600);

        // Sucursal B
        app()->instance('current_sucursal_id', $this->sucursalB->id);
        Cache::tenant()->put('catalogo_menu', 'Menu Exclusivo Sucursal B', 3600);

        // Verificación de aislamiento
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        $this->assertEquals('Menu Exclusivo Sucursal A', Cache::tenant()->get('catalogo_menu'));

        app()->instance('current_sucursal_id', $this->sucursalB->id);
        $this->assertEquals('Menu Exclusivo Sucursal B', Cache::tenant()->get('catalogo_menu'));

        // Limpieza
        Cache::tenant()->flush();
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        Cache::tenant()->flush();
        app()->forgetInstance('current_sucursal_id');
    }

    public function test_selective_flushing_only_purges_target_sucursal(): void
    {
        // Guardar caché en ambas sucursales
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        Cache::tenant()->put('precios_activos', ['tacos' => 50], 3600);

        app()->instance('current_sucursal_id', $this->sucursalB->id);
        Cache::tenant()->put('precios_activos', ['tacos' => 75], 3600);

        // Purgar únicamente Sucursal B
        app()->instance('current_sucursal_id', $this->sucursalB->id);
        Cache::tenant()->flush();

        // Sucursal B debe estar vacía
        $this->assertNull(Cache::tenant()->get('precios_activos'));

        // Sucursal A debe permanecer intacta
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        $this->assertEquals(['tacos' => 50], Cache::tenant()->get('precios_activos'));

        // Limpieza final
        Cache::tenant()->flush();
        app()->forgetInstance('current_sucursal_id');
    }

    public function test_dish_observer_selectively_flushes_cache_for_its_sucursal(): void
    {
        // Precargar caché en ambas sucursales
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        Cache::tenant()->put('menu_activo', 'Cache A Inicial', 3600);

        app()->instance('current_sucursal_id', $this->sucursalB->id);
        Cache::tenant()->put('menu_activo', 'Cache B Inicial', 3600);

        // Crear categoría en Sucursal A
        $catA = Category::create([
            'sucursal_id' => $this->sucursalA->id,
            'name'        => 'Platillos Fuertes',
            'slug'        => 'platillos-fuertes',
            'active'      => true,
        ]);

        // Crear platillo en Sucursal A (Dispara DishObserver::saved)
        Dish::create([
            'sucursal_id' => $this->sucursalA->id,
            'category_id' => $catA->id,
            'name'        => 'Filete Mignon',
            'slug'        => 'filete-mignon',
            'price'       => 350.00,
            'is_active'   => true,
        ]);

        // La caché de Sucursal A debió purgarse
        app()->instance('current_sucursal_id', $this->sucursalA->id);
        $this->assertNull(Cache::tenant()->get('menu_activo'));

        // La caché de Sucursal B debe continuar intacta y rápida
        app()->instance('current_sucursal_id', $this->sucursalB->id);
        $this->assertEquals('Cache B Inicial', Cache::tenant()->get('menu_activo'));

        // Limpieza
        Cache::tenant()->flush();
        app()->forgetInstance('current_sucursal_id');
    }
}
