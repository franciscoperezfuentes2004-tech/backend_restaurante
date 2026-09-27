<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dish;
use App\Models\Category;
use App\Events\LandingUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LandingUpdatedEventTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected Dish $dish1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Restaurante'
        ]);

        $this->category = Category::create([
            'name'        => 'Entradas',
            'slug'        => 'entradas',
            'description' => 'Entradas finas',
            'active'      => true,
            'is_active'   => true,
        ]);

        $this->dish1 = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Ceviche Mixto',
            'slug'         => 'ceviche-mixto',
            'price'        => 195.00,
            'is_active'    => true,
            'is_available' => true,
            'is_featured'  => true,
        ]);
    }

    /**
     * Test 1: El evento LandingUpdated implementa ShouldBroadcast y emite en 'public-landing'.
     */
    public function test_landing_updated_event_implements_should_broadcast_and_broadcasts_on_public_landing()
    {
        $event = new LandingUpdated(['restaurant_name' => 'AURUM TEST']);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);

        $channels = $event->broadcastOn();
        $channelNames = array_map(fn($ch) => $ch->name, $channels);

        $this->assertContains('public-landing', $channelNames);
        $this->assertEquals('landing_updated', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('settings', $payload);
        $this->assertEquals('AURUM TEST', $payload['settings']['restaurant_name']);
    }

    /**
     * Test 2: El evento se puede instanciar sin parámetros como indica la especificación quirúrgica.
     */
    public function test_landing_updated_event_instantiates_without_parameters()
    {
        $event = new LandingUpdated();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);

        $channels = $event->broadcastOn();
        $channelNames = array_map(fn($ch) => $ch->name, $channels);
        $this->assertContains('public-landing', $channelNames);

        $payload = $event->broadcastWith();
        $this->assertEquals('Landing Page actualizada', $payload['message']);
    }

    /**
     * Test 3: PUT /api/admin/settings/landing despacha LandingUpdated.
     */
    public function test_update_landing_page_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/landing', [
                'hero_title'          => 'El Arte Culinario',
                'hero_slogan'         => 'Experiencia gastronómica inolvidable',
                'enable_carousel'     => false,
                'history_title'       => 'Nuestra Historia',
                'history_description' => 'Fundado con pasión por la gastronomía mexicana contemporánea.',
                'foundation_year'     => 2020,
                'features'            => [
                    ['icon' => 'star', 'title' => 'Calidad', 'description' => 'Ingredientes frescos'],
                    ['icon' => 'heart', 'title' => 'Pasión', 'description' => 'Amor en cada platillo'],
                    ['icon' => 'gem', 'title' => 'Exclusividad', 'description' => 'Ambiente único'],
                ],
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class, function ($event) {
            $channels = array_map(fn($ch) => $ch->name, $event->broadcastOn());
            return in_array('public-landing', $channels);
        });
    }

    /**
     * Test 4: PUT /api/admin/settings/featured-dishes despacha LandingUpdated.
     */
    public function test_update_featured_dishes_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/featured-dishes', [
                'title'               => 'Nuestra Selección Gourmet',
                'featured_categories' => [$this->category->id],
                'featured_dishes'     => [$this->dish1->id]
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }

    /**
     * Test 5: PUT /api/admin/settings/exclusive-services despacha LandingUpdated.
     */
    public function test_update_exclusive_services_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/exclusive-services', [
                'services' => [
                    [
                        'id'          => 'banquetes',
                        'icon'        => 'MapPin',
                        'title'       => 'Banquetes Exclusivos',
                        'description' => 'Servicio para bodas y eventos especiales',
                    ],
                    [
                        'id'          => 'chef',
                        'icon'        => 'ChefHat',
                        'title'       => 'Chef en Casa',
                        'description' => 'Una experiencia gastronómica privada',
                    ],
                    [
                        'id'          => 'catas',
                        'icon'        => 'Star',
                        'title'       => 'Catas Privadas',
                        'description' => 'Selección de vinos y maridajes exclusivos',
                    ],
                ]
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }

    /**
     * Test 6: PUT /api/admin/settings/promo-banner despacha LandingUpdated.
     */
    public function test_update_promo_banner_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/promo-banner', [
                'active' => true,
                'text'   => '15% de descuento los jueves'
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }

    /**
     * Test 7: PUT /api/admin/settings/landing-reservations despacha LandingUpdated.
     */
    public function test_update_landing_reservations_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/landing-reservations', [
                'title'         => 'Reserve su Mesa',
                'subtitle'      => 'Experiencia Culinaria',
                'description'   => 'Disfrute de una velada excepcional con nuestro menú degustación.',
                'weekday_start' => '13:00',
                'weekday_end'   => '23:00',
                'weekend_start' => '12:00',
                'weekend_end'   => '00:00',
                'policies'      => [
                    'Tolerancia de 15 minutos en su reservación.',
                    'Código de vestimenta casual elegante.',
                    'Cancelaciones con al menos 2 horas de anticipación.',
                    'Mesas para más de 6 personas requieren confirmación.',
                ]
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }

    /**
     * Test 8: PUT /api/admin/settings/landing-contact despacha LandingUpdated.
     */
    public function test_update_landing_contact_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/landing-contact', [
                'title'           => 'Póngase en Contacto',
                'subtitle'        => 'Estamos para Servirle',
                'google_maps_url' => 'https://maps.google.com/?q=restaurante',
                'public_phone'    => '5512345678',
                'whatsapp_number' => '5512345678',
                'contact_email'   => 'contacto@gmail.com',
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }

    /**
     * Test 9: PUT /api/admin/settings/landing-delivery despacha LandingUpdated.
     */
    public function test_update_landing_delivery_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/landing-delivery', [
                'title'           => 'Servicio a Domicilio',
                'subtitle'        => 'De Nuestra Cocina a su Hogar',
                'description'     => 'Disfrute de nuestros platillos exclusivos sin salir de casa.',
                'whatsapp_number' => '5512345678',
                'button_subtext'  => 'Horario de entrega: 13:00 a 22:00 hrs',
                'benefits'        => [
                    ['title' => 'Empaque Térmico', 'description' => 'Mantiene la temperatura'],
                    ['title' => 'Puntualidad', 'description' => 'Entregas en tiempo'],
                    ['title' => 'Calidad', 'description' => 'Ingredientes selectos'],
                    ['title' => 'Seguimiento', 'description' => 'Monitoreo en tiempo real'],
                ],
                'steps'           => [
                    ['title' => 'Elija su Platillo', 'subtitle' => 'Explore nuestra carta'],
                    ['title' => 'Haga su Pedido', 'subtitle' => 'Por WhatsApp o plataforma'],
                    ['title' => 'Disfrute en Casa', 'subtitle' => 'Entregado en su puerta'],
                ],
                'guarantees'      => [
                    'Garantía de sabor',
                    'Empaque 100% biodegradable',
                    'Entrega higiénica y segura',
                ],
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }

    /**
     * Test 10: PUT /api/admin/configuracion despacha LandingUpdated.
     */
    public function test_update_configuracion_general_dispatches_landing_updated_event()
    {
        Event::fake([LandingUpdated::class]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/configuracion', [
                'business_name'      => 'Restaurante AURUM Gourmet',
                'theme'              => 'dark',
                'primary_color'      => '#D97706',
                'is_delivery_active' => false,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(LandingUpdated::class);
    }
}
