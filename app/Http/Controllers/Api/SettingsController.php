<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminCredentialsRequest;
use App\Http\Requests\UpdateDeliveryZoneRequest;
use App\Http\Requests\UpdateExclusiveServicesRequest;
use App\Http\Requests\UpdateFeaturedDishesRequest;
use App\Http\Requests\UpdateLandingContactRequest;
use App\Http\Requests\UpdateLandingDeliveryRequest;
use App\Http\Requests\UpdateLandingPageRequest;
use App\Http\Requests\UpdateLandingReservationsRequest;
use App\Http\Requests\UpdatePaymentMethodsRequest;
use App\Http\Requests\UpdatePromoBannerRequest;
use App\Models\RestaurantSetting;
use App\Services\AuditLogger;
use App\Services\ImageCompressionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function show()
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $configuracion = \App\Models\ConfiguracionGeneral::first();
        $fondoSistema = $configuracion?->fondo_sistema 
          ?? $configuracion?->color_fondo 
          ?? '#1C1917';
        $colorPrimario = $configuracion?->color_primario 
          ?? $settings->brand_color 
          ?? '#7c3aed';
        $colorApoyo = $configuracion?->color_apoyo 
          ?? $settings->secondary_color 
          ?? '#06b6d4';

        $data = $settings->toArray();

        // 1. Identidad General y Hero
        $data['restaurant_name'] = $settings->restaurant_name ?: 'AURUM';
        $data['name'] = $data['restaurant_name'];
        $data['nombre'] = $data['restaurant_name'];
        
        $data['logo_url'] = $settings->logo_url ?: ($configuracion?->logotipo ?: null);
        $data['logo'] = $data['logo_url'];
        
        $data['hero_title'] = $settings->hero_title ?: $data['restaurant_name'];
        $data['heroTitle'] = $data['hero_title'];
        
        $data['hero_slogan'] = $settings->hero_slogan ?: ($settings->hero_description ?: 'El mejor sabor de la ciudad');
        $data['hero_description'] = $data['hero_slogan'];
        $data['heroDescription'] = $data['hero_slogan'];
        
        $data['hero_image_url'] = $settings->hero_image_url ?: ($settings->hero_image ?: '/aurum_hero_dish.png');
        $data['hero_image'] = $data['hero_image_url'];
        $data['heroImage'] = $data['hero_image_url'];
        
        $data['location_text'] = $settings->location_text ?: ($settings->address ?: ($settings->city_state ?: 'Tu Ciudad, Estado'));
        $data['ubicacion'] = $data['location_text'];
        $data['address'] = $data['location_text'];
        
        // 2. Secciones del Menú y Delivery
        $data['menu_subtitle'] = $settings->menu_subtitle ?: 'NUESTRA CARTA';
        $data['menu_title'] = $settings->menu_title ?: 'Platillos que cuentan una historia';
        
        $data['delivery_title'] = $settings->delivery_title ?: ('Llevamos la experiencia ' . $data['restaurant_name'] . ' hasta tu hogar');
        $data['delivery_description'] = $settings->delivery_description ?: 'Entrega a domicilio con nuestros repartidores propios en 30-45 minutos. También puedes pasar a recoger tu pedido.';
        
        // 3. Botones y CTAs
        $data['cta_menu_text'] = $settings->cta_menu_text ?: 'VER MENÚ';
        $data['cta_reservation_text'] = $settings->cta_reservation_text ?: 'RESERVAR MESA';
        
        // 4. Contacto y Redes
        $contactPhone = $settings->contact_phone ?: ($settings->phone ?: ($settings->telefono ?: ($settings->telefono_publico ?: '744-XXX-XXXX')));
        $data['contact_phone'] = $contactPhone;
        $data['phone'] = $contactPhone;
        $data['telefono'] = $contactPhone;
        $data['telefono_publico'] = $contactPhone;
        
        $contactEmail = $settings->contact_email ?: ($settings->email ?: ($settings->correo ?: ($settings->correo_contacto ?: 'contacto@restaurante.com')));
        $data['contact_email'] = $contactEmail;
        $data['email'] = $contactEmail;
        $data['correo'] = $contactEmail;
        $data['correo_contacto'] = $contactEmail;
        
        $facebookUrl = $settings->facebook_url ?: ($settings->facebook ?: '');
        $data['facebook_url'] = $facebookUrl;
        $data['facebook'] = $facebookUrl;
        
        $instagramUrl = $settings->instagram_url ?: ($settings->instagram ?: '');
        $data['instagram_url'] = $instagramUrl;
        $data['instagram'] = $instagramUrl;
        
        $tiktokUrl = $settings->tiktok_url ?: ($settings->tiktok ?: '');
        $data['tiktok_url'] = $tiktokUrl;
        $data['tiktok'] = $tiktokUrl;
        
        $contactWhatsapp = $settings->contact_whatsapp ?: ($settings->whatsapp ?: $contactPhone);
        $data['contact_whatsapp'] = $contactWhatsapp;
        $data['whatsapp'] = $contactWhatsapp;
        $data['contact_whatsapp_url'] = $settings->contact_whatsapp_url ?: ('https://wa.me/' . (preg_replace('/[^0-9]/', '', $contactWhatsapp) ?: '527440000000'));
        
        // 5. Colores y Temas
        $data['fondo_sistema'] = $fondoSistema;
        $data['color_fondo'] = $fondoSistema;
        $data['color_primario'] = $colorPrimario;
        $data['brand_color'] = $colorPrimario;
        $data['color_apoyo'] = $colorApoyo;
        $data['secondary_color'] = $colorApoyo;

        // 6. Sección Nuestra Historia
        $historia = is_array($settings->historia_config) ? $settings->historia_config : [];
        $hTitulo = $historia['titulo'] ?? $historia['title'] ?? $settings->history_title ?? ('Historia de ' . $data['restaurant_name']);
        $hDesc = $historia['descripcion'] ?? $historia['description'] ?? $settings->history_description ?? '';
        $hAnio = $historia['anio'] ?? $historia['anioFundacion'] ?? $historia['year'] ?? $settings->history_year ?? null;
        $hFondo = $historia['fondo'] ?? $historia['imagenFondo'] ?? $historia['image'] ?? $settings->history_image ?? null;
        $hCaracteristicas = $historia['caracteristicas'] ?? $historia['features'] ?? $settings->history_features ?? [];

        $normalizedHistoria = [
            'titulo'          => $hTitulo,
            'title'           => $hTitulo,
            'descripcion'     => $hDesc,
            'description'     => $hDesc,
            'anio'            => $hAnio,
            'anioFundacion'   => $hAnio,
            'year'            => $hAnio,
            'fondo'           => $hFondo,
            'imagenFondo'     => $hFondo,
            'image'           => $hFondo,
            'caracteristicas' => $hCaracteristicas,
            'features'        => $hCaracteristicas,
        ];

        $data['historia_config'] = $normalizedHistoria;
        $data['historiaConfig'] = $normalizedHistoria;
        $data['history'] = $normalizedHistoria;
        $data['history_title'] = $hTitulo;
        $data['history_description'] = $hDesc;
        $data['history_year'] = $hAnio;
        $data['history_image'] = $hFondo;
        $data['history_features'] = $hCaracteristicas;

        // 7. Sección Platillos Destacados (Población de datos reales con relaciones)
        $platillosSeccion = is_array($settings->platillos_seccion) ? $settings->platillos_seccion : [];
        $rawDishIds = $settings->featured_dishes 
            ?? $platillosSeccion['selected_dishes'] 
            ?? $platillosSeccion['featured_dishes'] 
            ?? [];

        $rawCatIds = $settings->featured_categories 
            ?? $platillosSeccion['selected_categories'] 
            ?? $platillosSeccion['featured_categories'] 
            ?? [];

        $extractScalarIds = function ($items) {
            if (!is_array($items)) return [];
            $ids = [];
            foreach ($items as $item) {
                if (is_numeric($item)) {
                    $ids[] = (int) $item;
                } elseif (is_string($item) && is_numeric($item)) {
                    $ids[] = (int) $item;
                } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    $ids[] = (int) $item['id'];
                } elseif (is_object($item) && isset($item->id) && is_numeric($item->id)) {
                    $ids[] = (int) $item->id;
                }
            }
            return array_values(array_unique(array_filter($ids)));
        };

        $dishIds = $extractScalarIds($rawDishIds);
        $catIds = $extractScalarIds($rawCatIds);

        // Buscar platillos reales seleccionados con su categoría
        // PREVENCIÓN DE FALLBACK: Si $dishIds está vacío ([]), NO inventar datos ni consultar platillos aleatorios
        $featuredDishes = collect();
        if (!empty($dishIds)) {
            $featuredDishes = \App\Models\Dish::with('category')
                ->where('is_available', true)
                ->whereIn('id', $dishIds)
                ->get();
        }

        $featuredDishesData = $featuredDishes->map(function ($dish) {
            $cat = $dish->category;
            return [
                'id'            => $dish->id,
                'category_id'   => $dish->category_id,
                'name'          => $dish->name,
                'nombre'        => $dish->name,
                'slug'          => $dish->slug,
                'description'   => $dish->description,
                'descripcion'   => $dish->description,
                'price'         => (float) $dish->price,
                'precio'        => (float) $dish->price,
                'image_url'     => $dish->image_url,
                'imagen'        => $dish->image_url,
                'category_name' => $cat?->name ?? 'Menú',
                'category_slug' => $cat?->slug ?? 'menu',
                'category'      => $cat ? [
                    'id'          => $cat->id,
                    'name'        => $cat->name,
                    'slug'        => $cat->slug,
                    'description' => $cat->description,
                    'image_url'   => $cat->image_url,
                ] : null,
                'badge'         => $dish->is_featured ? 'Destacado' : null,
                'is_available'  => (bool) $dish->is_available,
                'is_featured'   => (bool) $dish->is_featured,
            ];
        })->values()->toArray();

        // Categorías correspondientes (estrictamente filtradas por $catIds, sin fallback)
        $featuredCats = collect();
        if (!empty($catIds)) {
            $featuredCats = \App\Models\Category::where('active', true)
                ->whereIn('id', $catIds)
                ->get();
        }

        $featuredCatsData = $featuredCats->map(function ($cat) {
            return [
                'id'          => $cat->id,
                'name'        => $cat->name,
                'nombre'      => $cat->name,
                'slug'        => $cat->slug,
                'description' => $cat->description,
                'image_url'   => $cat->image_url,
            ];
        })->values()->toArray();

        // Agrupación para tabs del menú
        $menuGrouped = [];
        foreach ($featuredDishesData as $dItem) {
            $cName = strtolower($dItem['category_name'] ?: 'platillos');
            if (!isset($menuGrouped[$cName])) {
                $menuGrouped[$cName] = [];
            }
            $menuGrouped[$cName][] = $dItem;
        }

        $platillosSeccion['selected_dishes'] = $dishIds;
        $platillosSeccion['selected_categories'] = $catIds;
        $platillosSeccion['dishes'] = $featuredDishesData;
        $platillosSeccion['categories'] = $featuredCatsData;

        // Populate directo esperado por el frontend
        $data['featured_dishes'] = $featuredDishesData;
        $data['featuredDishes'] = $featuredDishesData;
        $data['featured_categories'] = $featuredCatsData;
        $data['featuredCategories'] = $featuredCatsData;

        // IDs crudos para referencias
        $data['featured_dish_ids'] = $dishIds;
        $data['featuredDishIds'] = $dishIds;
        $data['featured_category_ids'] = $catIds;
        $data['featuredCategoryIds'] = $catIds;
        $data['selected_dishes'] = $dishIds;
        $data['selected_categories'] = $catIds;

        // Alias de compatibilidad
        $data['featured_dishes_data'] = $featuredDishesData;
        $data['featured_dishes_list'] = $featuredDishesData;
        $data['platillos_destacados'] = $featuredDishesData;
        $data['featured_categories_data'] = $featuredCatsData;
        $data['platillos_seccion'] = $platillosSeccion;
        $data['platillosSeccion'] = $platillosSeccion;
        $data['menu_destacados'] = $featuredDishesData;
        $data['menu_agrupado'] = $menuGrouped;

        // 8. Sección Delivery
        $deliverySeccion = is_array($settings->delivery_seccion) ? $settings->delivery_seccion : [];
        $deliveryImg = $settings->delivery_image_url 
            ?: ($deliverySeccion['delivery_image_url'] 
            ?? ($deliverySeccion['imagen'] 
            ?? ($deliverySeccion['imagen_url'] 
            ?? '/delivery.jpg')));

        $deliveryImgTitle = $settings->delivery_image_title 
            ?: ($deliverySeccion['delivery_image_title'] 
            ?? ($deliverySeccion['imagenTitulo'] 
            ?? ($deliverySeccion['imagen_titulo'] 
            ?? ($data['restaurant_name'] . ' EXPERIENCIA'))));

        $deliveryImgDesc = $settings->delivery_image_description 
            ?: ($deliverySeccion['delivery_image_description'] 
            ?? ($deliverySeccion['imagenDescripcion'] 
            ?? ($deliverySeccion['imagen_descripcion'] 
            ?? 'Servicio a domicilio premium')));

        $deliverySeccion['delivery_image_url'] = $deliveryImg;
        $deliverySeccion['imagen'] = $deliveryImg;
        $deliverySeccion['imagen_url'] = $deliveryImg;
        $deliverySeccion['delivery_image_title'] = $deliveryImgTitle;
        $deliverySeccion['imagenTitulo'] = $deliveryImgTitle;
        $deliverySeccion['imagen_titulo'] = $deliveryImgTitle;
        $deliverySeccion['delivery_image_description'] = $deliveryImgDesc;
        $deliverySeccion['imagenDescripcion'] = $deliveryImgDesc;
        $deliverySeccion['imagen_descripcion'] = $deliveryImgDesc;

        $data['delivery_image_url'] = $deliveryImg;
        $data['delivery_image_title'] = $deliveryImgTitle;
        $data['delivery_image_description'] = $deliveryImgDesc;
        $data['delivery_seccion'] = $deliverySeccion;
        $data['deliverySeccion'] = $deliverySeccion;

        // 9. Sección Contacto
        $contactoSeccion = is_array($settings->contacto_seccion) ? $settings->contacto_seccion : [];
        $contactoSeccion['titulo'] = $contactoSeccion['titulo'] ?? 'Encuéntranos';
        $contactoSeccion['labelSuperior'] = $contactoSeccion['labelSuperior'] ?? 'UBICACIÓN & CONTACTO';
        $contactoSeccion['textoEventos'] = $contactoSeccion['textoEventos'] ?? '';
        $contactoSeccion['mapsLink'] = $contactoSeccion['mapsLink'] ?? '';
        $contactoSeccion['telefono'] = $contactPhone;
        $contactoSeccion['email'] = $contactEmail;
        $contactoSeccion['whatsapp'] = $contactWhatsapp;
        $contactoSeccion['redesSociales'] = [
            'instagram' => $contactoSeccion['redesSociales']['instagram'] ?? $instagramUrl,
            'facebook'  => $contactoSeccion['redesSociales']['facebook'] ?? $facebookUrl,
            'tiktok'    => $contactoSeccion['redesSociales']['tiktok'] ?? $tiktokUrl,
        ];

        $data['contacto_seccion'] = $contactoSeccion;
        $data['contactoSeccion'] = $contactoSeccion;

        // 10. Métodos de Pago y Datos Bancarios
        $data['acepta_efectivo']       = (bool) ($settings->acepta_efectivo ?? true);
        $data['aceptaEfectivo']        = $data['acepta_efectivo'];
        $data['acepta_tarjeta']        = (bool) ($settings->acepta_tarjeta ?? true);
        $data['aceptaTarjeta']         = $data['acepta_tarjeta'];
        $data['acepta_transferencia']  = (bool) ($settings->acepta_transferencia ?? false);
        $data['aceptaTransferencia']   = $data['acepta_transferencia'];
        $data['banco_nombre']          = $settings->banco_nombre ?? '';
        $data['bancoNombre']           = $data['banco_nombre'];
        $data['banco_clabe']           = $settings->banco_clabe ?? '';
        $data['bancoClabe']            = $data['banco_clabe'];
        $data['banco_titular']         = $settings->banco_titular ?? '';
        $data['bancoTitular']          = $data['banco_titular'];

        // 11. Áreas reales activas para el selector de la Landing Page
        $data['areas'] = \App\Models\Area::select('id', 'nombre', 'name')
            ->where(function ($q) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('areas', 'is_active')) {
                    $q->where('is_active', true);
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('areas', 'active')) {
                    $q->orWhere('active', true);
                }
            })
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($area) {
                $nombre = $area->nombre ?: $area->name ?: ('Área ' . $area->id);
                return [
                    'id'     => $area->id,
                    'nombre' => $nombre,
                    'name'   => $nombre,
                ];
            });

        // 12. Horarios y Días Operativos de la Semana (0 = Domingo, 6 = Sábado)
        $activeDays = RestaurantSetting::getActiveOperatingDays();
        $operatingHours = RestaurantSetting::getOperatingHours();
        $operatingHours['active_days'] = $activeDays;
        $operatingHours['activeDays']  = $activeDays;
        $operatingHours['schedule']    = $settings->schedule ?? [];

        $data['active_days'] = $activeDays;
        $data['activeDays']  = $activeDays;
        $data['schedule']    = $settings->schedule ?? [];
        $data['horarios_atencion'] = $settings->schedule ?? [];
        $data['horarios']    = $operatingHours;
        $data['operating_hours'] = $operatingHours;

        // 13. Estadísticas Seguras de Experiencias y Reseñas (Zero-Trust Moderation)
        $data['stats_experiencias'] = \App\Models\Review::getExperienceStats();

        // 14. Integraciones de Notificaciones (Discord / Telegram)
        $data['active_notification_platform'] = $settings->active_notification_platform ?: 'none';
        $data['activeNotificationPlatform']  = $data['active_notification_platform'];

        $discordSettings = is_array($settings->discord_settings)
            ? $settings->discord_settings
            : (json_decode($settings->discord_settings ?? '[]', true) ?: []);

        $telegramSettings = is_array($settings->telegram_settings)
            ? $settings->telegram_settings
            : (json_decode($settings->telegram_settings ?? '[]', true) ?: []);

        $data['discord_settings'] = [
            'reservations'             => $discordSettings['reservations'] ?? $discordSettings['reservations_webhook_url'] ?? '',
            'system_alerts'            => $discordSettings['system_alerts'] ?? '',
            'inventory'                => $discordSettings['inventory'] ?? '',
            'cash_cuts'                => $discordSettings['cash_cuts'] ?? '',
            'general_admin'            => $discordSettings['general_admin'] ?? '',
            'daily_financial_report'   => $discordSettings['daily_financial_report'] ?? '',
            'reservations_webhook_url' => $discordSettings['reservations'] ?? $discordSettings['reservations_webhook_url'] ?? '',
        ];
        $data['discordSettings'] = $data['discord_settings'];

        $data['telegram_settings'] = [
            'bot_token'              => $telegramSettings['bot_token'] ?? '',
            'reservations'           => $telegramSettings['reservations'] ?? $telegramSettings['reservations_chat_id'] ?? '',
            'system_alerts'          => $telegramSettings['system_alerts'] ?? '',
            'inventory'              => $telegramSettings['inventory'] ?? '',
            'cash_cuts'              => $telegramSettings['cash_cuts'] ?? '',
            'general_admin'          => $telegramSettings['general_admin'] ?? '',
            'daily_financial_report' => $telegramSettings['daily_financial_report'] ?? '',
            'reservations_chat_id'   => $telegramSettings['reservations'] ?? $telegramSettings['reservations_chat_id'] ?? '',
        ];
        $data['telegramSettings'] = $data['telegram_settings'];

        return response()->json($data);
    }

    /**
     * Endpoint directo para consultar horarios y días operativos.
     */
    public function getSchedule()
    {
        $settings = RestaurantSetting::first();
        $activeDays = RestaurantSetting::getActiveOperatingDays();
        $operatingHours = RestaurantSetting::getOperatingHours();

        return response()->json([
            'active_days'     => $activeDays,
            'activeDays'      => $activeDays,
            'horarios'        => $operatingHours,
            'operating_hours' => $operatingHours,
            'schedule'        => $settings?->schedule ?? [],
        ]);
    }

    public function index()
    {
        return $this->show();
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // Identidad y textos generales
            'restaurant_name'        => 'nullable|string',
            'name'                   => 'nullable|string',
            'nombre'                 => 'nullable|string',
            'logo_url'               => 'nullable|string',
            'logo'                   => 'nullable|string',
            'description'            => 'nullable|string',
            'location_text'          => 'nullable|string',
            'locationText'           => 'nullable|string',
            'address'                => 'nullable|string',
            'city_state'             => 'nullable|string|max:100',
            'cityState'              => 'nullable|string',
            'municipio'              => 'nullable|string',
            'street_name'            => 'nullable|string|max:100',
            'streetName'             => 'nullable|string',
            'postal_code'            => 'nullable|string|max:10',
            'postalCode'             => 'nullable|string',

            // Hero section
            'hero_title'             => 'nullable|string',
            'heroTitle'              => 'nullable|string',
            'hero_slogan'            => 'nullable|string',
            'heroSlogan'             => 'nullable|string',
            'hero_description'       => 'nullable|string',
            'heroDescription'        => 'nullable|string',
            'hero_image_url'         => 'nullable|string',
            'heroImageUrl'           => 'nullable|string',
            'hero_image'             => 'nullable|string',
            'heroImage'              => 'nullable|string',
            'use_carousel'           => 'nullable|boolean',
            'useCarousel'            => 'nullable|boolean',
            'banner_images'          => 'nullable|array',
            'bannerImages'           => 'nullable|array',

            // Secciones Landing
            'menu_subtitle'          => 'nullable|string',
            'menuSubtitle'           => 'nullable|string',
            'menu_title'             => 'nullable|string',
            'menuTitle'              => 'nullable|string',
            'delivery_title'         => 'nullable|string',
            'deliveryTitle'          => 'nullable|string',
            'delivery_description'   => 'nullable|string',
            'deliveryDescription'    => 'nullable|string',
            'cta_menu_text'          => 'nullable|string',
            'ctaMenuText'            => 'nullable|string',
            'cta_reservation_text'   => 'nullable|string',
            'ctaReservationText'     => 'nullable|string',

            // Contacto y redes
            'contact_phone'          => 'nullable|string|max:50',
            'contactPhone'           => 'nullable|string|max:50',
            'phone'                  => 'nullable|string|max:50',
            'telefono'               => 'nullable|string|max:50',
            'telefono_publico'       => 'nullable|string|max:50',
            'telefonoPublico'        => 'nullable|string|max:50',
            'contact_email'          => 'nullable|email|max:255',
            'contactEmail'           => 'nullable|email|max:255',
            'email'                  => 'nullable|email|max:255',
            'correo'                 => 'nullable|email|max:255',
            'correo_contacto'        => 'nullable|email|max:255',
            'correoContacto'         => 'nullable|email|max:255',
            'facebook_url'           => 'nullable|string',
            'facebookUrl'            => 'nullable|string',
            'facebook'               => 'nullable|string',
            'instagram_url'          => 'nullable|string',
            'instagramUrl'           => 'nullable|string',
            'instagram'              => 'nullable|string',
            'tiktok_url'             => 'nullable|string',
            'tiktokUrl'              => 'nullable|string',
            'tiktok'                 => 'nullable|string',
            'contact_whatsapp'       => 'nullable|string|max:50',
            'contactWhatsapp'        => 'nullable|string|max:50',
            'whatsapp'               => 'nullable|string|max:50',
            'numeroWhatsapp'         => 'nullable|string|max:50',
            'numero_whatsapp'        => 'nullable|string|max:50',
            'contact_whatsapp_url'   => 'nullable|string',
            'contactWhatsappUrl'     => 'nullable|string',

            // Colores y diseño
            'brand_color'            => 'nullable|string',
            'brandColor'             => 'nullable|string',
            'color_primario'         => 'nullable|string',
            'colorPrimario'          => 'nullable|string',
            'secondary_color'        => 'nullable|string',
            'secondaryColor'         => 'nullable|string',
            'color_apoyo'            => 'nullable|string',
            'colorApoyo'             => 'nullable|string',
            'fondo_sistema'          => 'nullable|string',
            'fondoSistema'           => 'nullable|string',
            'color_fondo'            => 'nullable|string',

            // Métodos de pago y datos bancarios
            'acepta_efectivo'        => 'nullable|boolean',
            'aceptaEfectivo'         => 'nullable|boolean',
            'acepta_tarjeta'         => 'nullable|boolean',
            'aceptaTarjeta'          => 'nullable|boolean',
            'acepta_transferencia'   => 'nullable|boolean',
            'aceptaTransferencia'    => 'nullable|boolean',
            'banco_nombre'           => 'nullable|string|max:100',
            'bancoNombre'            => 'nullable|string|max:100',
            'banco_clabe'            => 'nullable|string|max:25',
            'bancoClabe'             => 'nullable|string|max:25',
            'banco_titular'          => 'nullable|string|max:150',
            'bancoTitular'           => 'nullable|string|max:150',

            // Integraciones de Notificaciones (Discord / Telegram)
            'active_notification_platform'              => 'nullable|string|in:none,discord,telegram',
            'activeNotificationPlatform'               => 'nullable|string|in:none,discord,telegram',
            'discord_settings'                          => 'nullable|array',
            'discordSettings'                           => 'nullable|array',
            'discord_settings.reservations'             => 'nullable|string|max:500',
            'discord_settings.system_alerts'            => 'nullable|string|max:500',
            'discord_settings.inventory'                => 'nullable|string|max:500',
            'discord_settings.cash_cuts'                => 'nullable|string|max:500',
            'discord_settings.general_admin'            => 'nullable|string|max:500',
            'discord_settings.daily_financial_report'   => 'nullable|string|max:500',
            'telegram_settings'                         => 'nullable|array',
            'telegramSettings'                          => 'nullable|array',
            'telegram_settings.bot_token'               => 'nullable|string|max:255',
            'telegram_settings.reservations'            => 'nullable|string|max:255',
            'telegram_settings.system_alerts'           => 'nullable|string|max:255',
            'telegram_settings.inventory'               => 'nullable|string|max:255',
            'telegram_settings.cash_cuts'               => 'nullable|string|max:255',
            'telegram_settings.general_admin'           => 'nullable|string|max:255',
            'telegram_settings.daily_financial_report'  => 'nullable|string|max:255',

            // Delivery & Operaciones
            'delivery_fee'           => 'nullable|numeric|min:0',
            'deliveryFee'            => 'nullable|numeric|min:0',
            'free_delivery_over'     => 'nullable|numeric|min:0',
            'freeDeliveryOver'       => 'nullable|numeric|min:0',
            'delivery_radius_meters' => 'nullable|integer|min:0',
            'deliveryRadiusMeters'   => 'nullable|integer|min:0',
            'delivery_radius_km'     => 'nullable|numeric|min:0.5|max:100',
            'coverage_polygon'       => 'nullable|array',
            'coveragePolygon'        => 'nullable|array',
            'coverage_neighborhoods' => 'nullable|array',
            'coverageNeighborhoods'  => 'nullable|array',
            'latitude'               => 'nullable|numeric|between:-90,90',
            'longitude'              => 'nullable|numeric|between:-180,180',
            'schedule'               => 'nullable|array',
            'cover_images'           => 'nullable|array',
            'coverImages'            => 'nullable|array',

            // Bloques JSON Landing & Nuestra Historia & Platillos Destacados
            'platillos_seccion'      => 'nullable|array',
            'platillosSeccion'       => 'nullable|array',
            'featured_categories'    => 'nullable|array',
            'featuredCategories'     => 'nullable|array',
            'featured_dishes'        => 'nullable|array',
            'featuredDishes'         => 'nullable|array',
            'delivery_seccion'       => 'nullable|array',
            'deliverySeccion'        => 'nullable|array',
            'delivery_image_url'     => 'nullable|string',
            'deliveryImageUrl'       => 'nullable|string',
            'delivery_image_title'   => 'nullable|string',
            'deliveryImageTitle'     => 'nullable|string',
            'imagen_titulo'          => 'nullable|string',
            'imagenTitulo'           => 'nullable|string',
            'delivery_image_description' => 'nullable|string',
            'deliveryImageDescription' => 'nullable|string',
            'delivery_image'         => 'nullable|string',
            'deliveryImage'          => 'nullable|string',
            'banner_descuento'       => 'nullable|array',
            'reservaciones_seccion'  => 'nullable|array',
            'historia_config'        => 'nullable|array',
            'historiaConfig'         => 'nullable|array',
            'history'                => 'nullable|array',
            'servicios_config'       => 'nullable|array',
            'contacto_seccion'       => 'nullable|array',
            'history_title'          => 'nullable|string',
            'historyTitle'           => 'nullable|string',
            'historia_titulo'        => 'nullable|string',
            'history_description'    => 'nullable|string',
            'historyDescription'     => 'nullable|string',
            'historia_descripcion'   => 'nullable|string',
            'history_year'           => 'nullable|integer',
            'historyYear'            => 'nullable|integer',
            'historia_anio'          => 'nullable|integer',
            'anio_fundacion'         => 'nullable|integer',
            'anioFundacion'          => 'nullable|integer',
            'history_image'          => 'nullable|string',
            'historyImage'           => 'nullable|string',
            'history_image_url'      => 'nullable|string',
            'historyImageUrl'        => 'nullable|string',
            'imagen_fondo'           => 'nullable|string',
            'imagenFondo'            => 'nullable|string',
            'fondo'                  => 'nullable|string',
            'history_features'       => 'nullable|array',
            'historyFeatures'        => 'nullable|array',
            'caracteristicas'        => 'nullable|array',
            'features'               => 'nullable|array',
        ]);

        $settings = RestaurantSetting::firstOrCreate([]);
        $updateData = [];

        // 1. Identidad
        if (isset($data['restaurant_name'])) $updateData['restaurant_name'] = $data['restaurant_name'];
        elseif (isset($data['name']))        $updateData['restaurant_name'] = $data['name'];
        elseif (isset($data['nombre']))      $updateData['restaurant_name'] = $data['nombre'];

        if (isset($data['logo_url'])) $updateData['logo_url'] = $data['logo_url'];
        elseif (isset($data['logo'])) $updateData['logo_url'] = $data['logo'];

        if (isset($data['description'])) $updateData['description'] = $data['description'];

        if (isset($data['location_text']))      $updateData['location_text'] = $data['location_text'];
        elseif (isset($data['locationText']))   $updateData['location_text'] = $data['locationText'];
        elseif (isset($data['address']))        $updateData['location_text'] = $data['address'];

        if (isset($data['address']))            $updateData['address'] = $data['address'];
        elseif (isset($data['location_text']))  $updateData['address'] = $data['location_text'];
        elseif (isset($data['locationText']))   $updateData['address'] = $data['locationText'];

        if (isset($data['city_state']))         $updateData['city_state'] = $data['city_state'];
        elseif (isset($data['cityState']))      $updateData['city_state'] = $data['cityState'];

        if (array_key_exists('municipio', $data)) $updateData['municipio'] = $data['municipio'];

        if (isset($data['street_name']))        $updateData['street_name'] = $data['street_name'];
        elseif (isset($data['streetName']))     $updateData['street_name'] = $data['streetName'];

        if (isset($data['postal_code']))        $updateData['postal_code'] = $data['postal_code'];
        elseif (isset($data['postalCode']))     $updateData['postal_code'] = $data['postalCode'];

        // 2. Hero
        if (isset($data['hero_title']))         $updateData['hero_title'] = $data['hero_title'];
        elseif (isset($data['heroTitle']))      $updateData['hero_title'] = $data['heroTitle'];

        if (isset($data['hero_slogan']))             $updateData['hero_slogan'] = $data['hero_slogan'];
        elseif (isset($data['heroSlogan']))          $updateData['hero_slogan'] = $data['heroSlogan'];
        elseif (isset($data['hero_description']))    $updateData['hero_slogan'] = $data['hero_description'];
        elseif (isset($data['heroDescription']))     $updateData['hero_slogan'] = $data['heroDescription'];

        if (isset($data['hero_description']))        $updateData['hero_description'] = $data['hero_description'];
        elseif (isset($data['heroDescription']))     $updateData['hero_description'] = $data['heroDescription'];
        elseif (isset($data['hero_slogan']))         $updateData['hero_description'] = $data['hero_slogan'];
        elseif (isset($data['heroSlogan']))          $updateData['hero_description'] = $data['heroSlogan'];

        if (isset($data['hero_image_url']))          $updateData['hero_image_url'] = $data['hero_image_url'];
        elseif (isset($data['heroImageUrl']))        $updateData['hero_image_url'] = $data['heroImageUrl'];
        elseif (isset($data['hero_image']))          $updateData['hero_image_url'] = $data['hero_image'];
        elseif (isset($data['heroImage']))           $updateData['hero_image_url'] = $data['heroImage'];

        if (isset($data['hero_image']))              $updateData['hero_image'] = $data['hero_image'];
        elseif (isset($data['heroImage']))           $updateData['hero_image'] = $data['heroImage'];
        elseif (isset($data['hero_image_url']))      $updateData['hero_image'] = $data['hero_image_url'];
        elseif (isset($data['heroImageUrl']))        $updateData['hero_image'] = $data['heroImageUrl'];

        if (array_key_exists('use_carousel', $data)) $updateData['use_carousel'] = $data['use_carousel'];
        elseif (array_key_exists('useCarousel', $data)) $updateData['use_carousel'] = $data['useCarousel'];

        if (isset($data['banner_images']))      $updateData['banner_images'] = $data['banner_images'];
        elseif (isset($data['bannerImages']))   $updateData['banner_images'] = $data['bannerImages'];

        // 3. Secciones Landing
        if (isset($data['menu_subtitle']))      $updateData['menu_subtitle'] = $data['menu_subtitle'];
        elseif (isset($data['menuSubtitle']))   $updateData['menu_subtitle'] = $data['menuSubtitle'];

        if (isset($data['menu_title']))         $updateData['menu_title'] = $data['menu_title'];
        elseif (isset($data['menuTitle']))      $updateData['menu_title'] = $data['menuTitle'];

        if (isset($data['delivery_title']))     $updateData['delivery_title'] = $data['delivery_title'];
        elseif (isset($data['deliveryTitle']))  $updateData['delivery_title'] = $data['deliveryTitle'];

        if (isset($data['delivery_description']))    $updateData['delivery_description'] = $data['delivery_description'];
        elseif (isset($data['deliveryDescription'])) $updateData['delivery_description'] = $data['deliveryDescription'];

        if (isset($data['cta_menu_text']))      $updateData['cta_menu_text'] = $data['cta_menu_text'];
        elseif (isset($data['ctaMenuText']))    $updateData['cta_menu_text'] = $data['ctaMenuText'];

        if (isset($data['cta_reservation_text']))    $updateData['cta_reservation_text'] = $data['cta_reservation_text'];
        elseif (isset($data['ctaReservationText']))  $updateData['cta_reservation_text'] = $data['ctaReservationText'];

        // 4. Contacto y Redes
        $phoneInput = $data['contact_phone'] ?? $data['contactPhone'] ?? $data['phone'] ?? $data['telefono'] ?? $data['telefono_publico'] ?? $data['telefonoPublico'] ?? null;
        if ($phoneInput !== null) {
            $updateData['contact_phone']    = $phoneInput;
            $updateData['phone']            = $phoneInput;
            $updateData['telefono']         = $phoneInput;
            $updateData['telefono_publico'] = $phoneInput;
        }

        $emailInput = $data['contact_email'] ?? $data['contactEmail'] ?? $data['email'] ?? $data['correo'] ?? $data['correo_contacto'] ?? $data['correoContacto'] ?? null;
        if ($emailInput !== null) {
            $updateData['contact_email']   = $emailInput;
            $updateData['email']           = $emailInput;
            $updateData['correo']          = $emailInput;
            $updateData['correo_contacto'] = $emailInput;
        }

        $whatsappInput = $data['contact_whatsapp'] ?? $data['contactWhatsapp'] ?? $data['whatsapp'] ?? $data['numeroWhatsapp'] ?? $data['numero_whatsapp'] ?? null;
        if ($whatsappInput !== null) {
            $updateData['contact_whatsapp'] = $whatsappInput;
            $updateData['whatsapp']         = $whatsappInput;
            $updateData['numero_whatsapp']  = $whatsappInput;
        }

        if (isset($data['contact_whatsapp_url']))   $updateData['contact_whatsapp_url'] = $data['contact_whatsapp_url'];
        elseif (isset($data['contactWhatsappUrl'])) $updateData['contact_whatsapp_url'] = $data['contactWhatsappUrl'];

        if (isset($data['facebook_url']))       $updateData['facebook_url'] = $data['facebook_url'];
        elseif (isset($data['facebookUrl']))    $updateData['facebook_url'] = $data['facebookUrl'];
        elseif (isset($data['facebook']))       $updateData['facebook_url'] = $data['facebook'];

        if (isset($data['facebook']))           $updateData['facebook'] = $data['facebook'];
        elseif (isset($data['facebook_url']))   $updateData['facebook'] = $data['facebook_url'];
        elseif (isset($data['facebookUrl']))    $updateData['facebook'] = $data['facebookUrl'];

        if (isset($data['instagram_url']))      $updateData['instagram_url'] = $data['instagram_url'];
        elseif (isset($data['instagramUrl']))   $updateData['instagram_url'] = $data['instagramUrl'];
        elseif (isset($data['instagram']))      $updateData['instagram_url'] = $data['instagram'];

        if (isset($data['instagram']))          $updateData['instagram'] = $data['instagram'];
        elseif (isset($data['instagram_url']))  $updateData['instagram'] = $data['instagram_url'];
        elseif (isset($data['instagramUrl']))   $updateData['instagram'] = $data['instagramUrl'];

        if (isset($data['tiktok_url']))         $updateData['tiktok_url'] = $data['tiktok_url'];
        elseif (isset($data['tiktokUrl']))      $updateData['tiktok_url'] = $data['tiktokUrl'];
        elseif (isset($data['tiktok']))         $updateData['tiktok_url'] = $data['tiktok'];

        if (isset($data['tiktok']))             $updateData['tiktok'] = $data['tiktok'];
        elseif (isset($data['tiktok_url']))     $updateData['tiktok'] = $data['tiktok_url'];
        elseif (isset($data['tiktokUrl']))      $updateData['tiktok'] = $data['tiktokUrl'];

        if (isset($data['whatsapp']))           $updateData['whatsapp'] = $data['whatsapp'];
        elseif (isset($data['contact_whatsapp'])) $updateData['whatsapp'] = $data['contact_whatsapp'];
        elseif (isset($data['contactWhatsapp'])) $updateData['whatsapp'] = $data['contactWhatsapp'];

        if (isset($data['contact_whatsapp_url']))   $updateData['contact_whatsapp_url'] = $data['contact_whatsapp_url'];
        elseif (isset($data['contactWhatsappUrl'])) $updateData['contact_whatsapp_url'] = $data['contactWhatsappUrl'];

        // 5. Colores
        if (isset($data['brand_color']))        $updateData['brand_color'] = $data['brand_color'];
        elseif (isset($data['brandColor']))     $updateData['brand_color'] = $data['brandColor'];
        elseif (isset($data['color_primario'])) $updateData['brand_color'] = $data['color_primario'];
        elseif (isset($data['colorPrimario']))  $updateData['brand_color'] = $data['colorPrimario'];

        if (isset($data['secondary_color']))    $updateData['secondary_color'] = $data['secondary_color'];
        elseif (isset($data['secondaryColor'])) $updateData['secondary_color'] = $data['secondaryColor'];
        elseif (isset($data['color_apoyo']))    $updateData['secondary_color'] = $data['color_apoyo'];
        elseif (isset($data['colorApoyo']))     $updateData['secondary_color'] = $data['colorApoyo'];

        // 6. Métodos de Pago y Datos Bancarios
        if (array_key_exists('acepta_efectivo', $data)) {
            $updateData['acepta_efectivo'] = (bool) $data['acepta_efectivo'];
        } elseif (array_key_exists('aceptaEfectivo', $data)) {
            $updateData['acepta_efectivo'] = (bool) $data['aceptaEfectivo'];
        }

        if (array_key_exists('acepta_tarjeta', $data)) {
            $updateData['acepta_tarjeta'] = (bool) $data['acepta_tarjeta'];
        } elseif (array_key_exists('aceptaTarjeta', $data)) {
            $updateData['acepta_tarjeta'] = (bool) $data['aceptaTarjeta'];
        }

        if (array_key_exists('acepta_transferencia', $data)) {
            $updateData['acepta_transferencia'] = (bool) $data['acepta_transferencia'];
        } elseif (array_key_exists('aceptaTransferencia', $data)) {
            $updateData['acepta_transferencia'] = (bool) $data['aceptaTransferencia'];
        }

        if (array_key_exists('banco_nombre', $data))        $updateData['banco_nombre'] = $data['banco_nombre'];
        elseif (array_key_exists('bancoNombre', $data))     $updateData['banco_nombre'] = $data['bancoNombre'];

        if (array_key_exists('banco_clabe', $data))         $updateData['banco_clabe'] = $data['banco_clabe'];
        elseif (array_key_exists('bancoClabe', $data))      $updateData['banco_clabe'] = $data['bancoClabe'];

        if (array_key_exists('banco_titular', $data))       $updateData['banco_titular'] = $data['banco_titular'];
        elseif (array_key_exists('bancoTitular', $data))    $updateData['banco_titular'] = $data['bancoTitular'];

        // Integraciones de Notificaciones (Discord / Telegram)
        if (array_key_exists('active_notification_platform', $data)) {
            $updateData['active_notification_platform'] = $data['active_notification_platform'] ?: 'none';
        } elseif (array_key_exists('activeNotificationPlatform', $data)) {
            $updateData['active_notification_platform'] = $data['activeNotificationPlatform'] ?: 'none';
        }

        if (array_key_exists('discord_settings', $data) || array_key_exists('discordSettings', $data)) {
            $ds = $data['discord_settings'] ?? $data['discordSettings'] ?? [];
            if (is_string($ds)) {
                $ds = json_decode($ds, true) ?: [];
            }
            $updateData['discord_settings'] = [
                'reservations'           => $ds['reservations'] ?? null,
                'system_alerts'          => $ds['system_alerts'] ?? null,
                'inventory'              => $ds['inventory'] ?? null,
                'cash_cuts'              => $ds['cash_cuts'] ?? null,
                'general_admin'          => $ds['general_admin'] ?? null,
                'daily_financial_report' => $ds['daily_financial_report'] ?? null,
            ];
        }

        if (array_key_exists('telegram_settings', $data) || array_key_exists('telegramSettings', $data)) {
            $ts = $data['telegram_settings'] ?? $data['telegramSettings'] ?? [];
            if (is_string($ts)) {
                $ts = json_decode($ts, true) ?: [];
            }
            $updateData['telegram_settings'] = [
                'bot_token'              => $ts['bot_token'] ?? $ts['botToken'] ?? null,
                'reservations'           => $ts['reservations'] ?? null,
                'system_alerts'          => $ts['system_alerts'] ?? null,
                'inventory'              => $ts['inventory'] ?? null,
                'cash_cuts'              => $ts['cash_cuts'] ?? null,
                'general_admin'          => $ts['general_admin'] ?? null,
                'daily_financial_report' => $ts['daily_financial_report'] ?? null,
            ];
        }

        // 7. Operaciones & Delivery
        if (array_key_exists('delivery_fee', $data))        $updateData['delivery_fee'] = $data['delivery_fee'];
        elseif (array_key_exists('deliveryFee', $data))     $updateData['delivery_fee'] = $data['deliveryFee'];

        if (array_key_exists('free_delivery_over', $data))  $updateData['free_delivery_over'] = $data['free_delivery_over'];
        elseif (array_key_exists('freeDeliveryOver', $data))$updateData['free_delivery_over'] = $data['freeDeliveryOver'];

        if (array_key_exists('delivery_radius_meters', $data))  $updateData['delivery_radius_meters'] = $data['delivery_radius_meters'];
        elseif (array_key_exists('deliveryRadiusMeters', $data))$updateData['delivery_radius_meters'] = $data['deliveryRadiusMeters'];

        if (isset($data['delivery_radius_km'])) $updateData['delivery_radius_km'] = $data['delivery_radius_km'];
        if (isset($data['latitude']))           $updateData['latitude'] = $data['latitude'];
        if (isset($data['longitude']))          $updateData['longitude'] = $data['longitude'];
        if (isset($data['schedule']))           $updateData['schedule'] = $data['schedule'];

        if (isset($data['cover_images']))       $updateData['cover_images'] = $data['cover_images'];
        elseif (isset($data['coverImages']))    $updateData['cover_images'] = $data['coverImages'];

        if (isset($data['coverage_polygon']))   $updateData['coverage_polygon'] = $data['coverage_polygon'];
        elseif (isset($data['coveragePolygon']))$updateData['coverage_polygon'] = $data['coveragePolygon'];

        // 7. Bloques JSON Landing & Platillos Destacados
        $rawPlatillos = $data['platillos_seccion'] ?? $data['platillosSeccion'] ?? null;
        $featuredDishesInput = $data['featured_dishes'] 
            ?? $data['featuredDishes'] 
            ?? (is_array($rawPlatillos) ? ($rawPlatillos['selected_dishes'] ?? $rawPlatillos['featured_dishes'] ?? null) : null);

        $featuredCatsInput = $data['featured_categories'] 
            ?? $data['featuredCategories'] 
            ?? (is_array($rawPlatillos) ? ($rawPlatillos['selected_categories'] ?? $rawPlatillos['featured_categories'] ?? null) : null);

        $extractScalarIds = function ($items) {
            if (!is_array($items)) return [];
            $ids = [];
            foreach ($items as $item) {
                if (is_numeric($item)) {
                    $ids[] = (int) $item;
                } elseif (is_string($item) && is_numeric($item)) {
                    $ids[] = (int) $item;
                } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    $ids[] = (int) $item['id'];
                } elseif (is_object($item) && isset($item->id) && is_numeric($item->id)) {
                    $ids[] = (int) $item->id;
                }
            }
            return array_values(array_unique(array_filter($ids)));
        };

        if ($featuredDishesInput !== null) {
            $updateData['featured_dishes'] = $extractScalarIds($featuredDishesInput);
        }

        if ($featuredCatsInput !== null) {
            $updateData['featured_categories'] = $extractScalarIds($featuredCatsInput);
        }

        if ($rawPlatillos !== null || $featuredDishesInput !== null || $featuredCatsInput !== null) {
            $existingPlatillos = is_array($settings->platillos_seccion) ? $settings->platillos_seccion : [];
            $mergedPlatillos = is_array($rawPlatillos) ? array_merge($existingPlatillos, $rawPlatillos) : $existingPlatillos;

            if ($featuredDishesInput !== null) {
                $mergedPlatillos['selected_dishes'] = $updateData['featured_dishes'] ?? [];
                $mergedPlatillos['featured_dishes'] = $mergedPlatillos['selected_dishes'];
            } elseif (isset($existingPlatillos['selected_dishes']) || isset($settings->featured_dishes)) {
                $mergedPlatillos['selected_dishes'] = $extractScalarIds($existingPlatillos['selected_dishes'] ?? $settings->featured_dishes);
                $mergedPlatillos['featured_dishes'] = $mergedPlatillos['selected_dishes'];
            }

            if ($featuredCatsInput !== null) {
                $mergedPlatillos['selected_categories'] = $updateData['featured_categories'] ?? [];
                $mergedPlatillos['featured_categories'] = $mergedPlatillos['selected_categories'];
            } elseif (isset($existingPlatillos['selected_categories']) || isset($settings->featured_categories)) {
                $mergedPlatillos['selected_categories'] = $extractScalarIds($existingPlatillos['selected_categories'] ?? $settings->featured_categories);
                $mergedPlatillos['featured_categories'] = $mergedPlatillos['selected_categories'];
            }

            $updateData['platillos_seccion'] = $mergedPlatillos;
        }

        // 8. Sección Delivery e Imágenes (Merge Seguro)
        $rawDelivery = $data['delivery_seccion'] ?? $data['deliverySeccion'] ?? null;
        $existingDelivery = is_array($settings->delivery_seccion) ? $settings->delivery_seccion : [];

        $deliveryImgInput = $data['delivery_image_url'] 
            ?? $data['deliveryImageUrl'] 
            ?? $data['delivery_image'] 
            ?? $data['deliveryImage'] 
            ?? (is_array($rawDelivery) ? ($rawDelivery['delivery_image_url'] ?? $rawDelivery['imagen'] ?? $rawDelivery['imagen_url'] ?? null) : null);

        $deliveryImgTitleInput = $data['delivery_image_title'] 
            ?? $data['deliveryImageTitle'] 
            ?? $data['imagen_titulo'] 
            ?? $data['imagenTitulo'] 
            ?? (is_array($rawDelivery) ? ($rawDelivery['delivery_image_title'] ?? $rawDelivery['imagenTitulo'] ?? $rawDelivery['imagen_titulo'] ?? null) : null);

        $deliveryImgDescInput = $data['delivery_image_description'] 
            ?? $data['deliveryImageDescription'] 
            ?? (is_array($rawDelivery) ? ($rawDelivery['delivery_image_description'] ?? $rawDelivery['imagenDescripcion'] ?? $rawDelivery['imagen_descripcion'] ?? null) : null);

        // Pre-fill con los valores existentes en BD para nunca perder imágenes ni textos al actualizar solo una parte
        $finalDeliveryImg = (!empty($deliveryImgInput)) 
            ? $deliveryImgInput 
            : ($settings->delivery_image_url 
            ?: ($existingDelivery['delivery_image_url'] 
            ?? ($existingDelivery['imagen'] 
            ?? ($existingDelivery['imagen_url'] ?? null))));

        $finalDeliveryTitle = (!empty($deliveryImgTitleInput)) 
            ? $deliveryImgTitleInput 
            : ($settings->delivery_image_title 
            ?: ($existingDelivery['delivery_image_title'] 
            ?? ($existingDelivery['imagenTitulo'] 
            ?? ($existingDelivery['imagen_titulo'] ?? null))));

        $finalDeliveryDesc = (!empty($deliveryImgDescInput)) 
            ? $deliveryImgDescInput 
            : ($settings->delivery_image_description 
            ?: ($existingDelivery['delivery_image_description'] 
            ?? ($existingDelivery['imagenDescripcion'] 
            ?? ($existingDelivery['imagen_descripcion'] ?? null))));

        if (!empty($finalDeliveryImg)) {
            $updateData['delivery_image_url'] = $finalDeliveryImg;
        }

        if (!empty($finalDeliveryTitle)) {
            $updateData['delivery_image_title'] = $finalDeliveryTitle;
        }

        if (!empty($finalDeliveryDesc)) {
            $updateData['delivery_image_description'] = $finalDeliveryDesc;
        }

        if ($rawDelivery !== null || $deliveryImgInput !== null || $deliveryImgTitleInput !== null || $deliveryImgDescInput !== null) {
            $mergedDelivery = is_array($rawDelivery) ? array_merge($existingDelivery, $rawDelivery) : $existingDelivery;

            if (!empty($finalDeliveryImg)) {
                $mergedDelivery['delivery_image_url'] = $finalDeliveryImg;
                $mergedDelivery['imagen'] = $finalDeliveryImg;
                $mergedDelivery['imagen_url'] = $finalDeliveryImg;
            }
            if (!empty($finalDeliveryTitle)) {
                $mergedDelivery['delivery_image_title'] = $finalDeliveryTitle;
                $mergedDelivery['imagenTitulo'] = $finalDeliveryTitle;
                $mergedDelivery['imagen_titulo'] = $finalDeliveryTitle;
            }
            if (!empty($finalDeliveryDesc)) {
                $mergedDelivery['delivery_image_description'] = $finalDeliveryDesc;
                $mergedDelivery['imagenDescripcion'] = $finalDeliveryDesc;
                $mergedDelivery['imagen_descripcion'] = $finalDeliveryDesc;
            }

            $updateData['delivery_seccion'] = $mergedDelivery;
        }

        if (isset($data['banner_descuento'])) {
            $existingBanner = is_array($settings->banner_descuento) ? $settings->banner_descuento : [];
            $updateData['banner_descuento'] = is_array($data['banner_descuento']) ? array_merge($existingBanner, $data['banner_descuento']) : $data['banner_descuento'];
        }

        if (isset($data['reservaciones_seccion'])) {
            $existingRes = is_array($settings->reservaciones_seccion) ? $settings->reservaciones_seccion : [];
            $updateData['reservaciones_seccion'] = is_array($data['reservaciones_seccion']) ? array_merge($existingRes, $data['reservaciones_seccion']) : $data['reservaciones_seccion'];
        }

        if (isset($data['servicios_config'])) {
            $updateData['servicios_config'] = $data['servicios_config'];
        }

        if (isset($data['contacto_seccion'])) {
            $existingCont = is_array($settings->contacto_seccion) ? $settings->contacto_seccion : [];
            $incomingCont = is_array($data['contacto_seccion']) ? $data['contacto_seccion'] : [];
            $mergedCont = array_merge($existingCont, $incomingCont);

            if (isset($incomingCont['telefono']) && !isset($updateData['phone'])) {
                $updateData['phone'] = $incomingCont['telefono'];
                $updateData['contact_phone'] = $incomingCont['telefono'];
                $updateData['telefono'] = $incomingCont['telefono'];
                $updateData['telefono_publico'] = $incomingCont['telefono'];
            }
            if (isset($incomingCont['email']) && !isset($updateData['email'])) {
                $updateData['email'] = $incomingCont['email'];
                $updateData['contact_email'] = $incomingCont['email'];
                $updateData['correo'] = $incomingCont['email'];
                $updateData['correo_contacto'] = $incomingCont['email'];
            }
            if (isset($incomingCont['whatsapp']) && !isset($updateData['whatsapp'])) {
                $updateData['whatsapp'] = $incomingCont['whatsapp'];
                $updateData['contact_whatsapp'] = $incomingCont['whatsapp'];
                $updateData['numero_whatsapp'] = $incomingCont['whatsapp'];
            }
            if (isset($incomingCont['redesSociales']) && is_array($incomingCont['redesSociales'])) {
                if (isset($incomingCont['redesSociales']['instagram']) && !isset($updateData['instagram_url'])) {
                    $updateData['instagram_url'] = $incomingCont['redesSociales']['instagram'];
                    $updateData['instagram'] = $incomingCont['redesSociales']['instagram'];
                }
                if (isset($incomingCont['redesSociales']['facebook']) && !isset($updateData['facebook_url'])) {
                    $updateData['facebook_url'] = $incomingCont['redesSociales']['facebook'];
                    $updateData['facebook'] = $incomingCont['redesSociales']['facebook'];
                }
                if (isset($incomingCont['redesSociales']['tiktok']) && !isset($updateData['tiktok_url'])) {
                    $updateData['tiktok_url'] = $incomingCont['redesSociales']['tiktok'];
                    $updateData['tiktok'] = $incomingCont['redesSociales']['tiktok'];
                }
            }

            $updateData['contacto_seccion'] = $mergedCont;
        }

        // Procesamiento de Nuestra Historia (Merge Seguro)
        $rawHistoria = $data['historia_config'] ?? $data['historiaConfig'] ?? $data['history'] ?? null;
        $existingHistoria = is_array($settings->historia_config) ? $settings->historia_config : [];

        $historyTitle = $data['history_title'] 
            ?? $data['historyTitle'] 
            ?? $data['historia_titulo'] 
            ?? (is_array($rawHistoria) ? ($rawHistoria['titulo'] ?? $rawHistoria['title'] ?? $rawHistoria['history_title'] ?? null) : null);

        $historyDesc = $data['history_description'] 
            ?? $data['historyDescription'] 
            ?? $data['historia_descripcion'] 
            ?? (is_array($rawHistoria) ? ($rawHistoria['descripcion'] ?? $rawHistoria['description'] ?? $rawHistoria['history_description'] ?? null) : null);

        $historyYear = $data['history_year'] 
            ?? $data['historyYear'] 
            ?? $data['historia_anio'] 
            ?? $data['anio_fundacion'] 
            ?? $data['anioFundacion'] 
            ?? (is_array($rawHistoria) ? ($rawHistoria['anio'] ?? $rawHistoria['anioFundacion'] ?? $rawHistoria['year'] ?? $rawHistoria['history_year'] ?? null) : null);

        $historyImg = $data['history_image'] 
            ?? $data['historyImage'] 
            ?? $data['history_image_url'] 
            ?? $data['historyImageUrl'] 
            ?? $data['imagen_fondo'] 
            ?? $data['imagenFondo'] 
            ?? $data['fondo'] 
            ?? (is_array($rawHistoria) ? ($rawHistoria['fondo'] ?? $rawHistoria['imagenFondo'] ?? $rawHistoria['image'] ?? $rawHistoria['history_image'] ?? null) : null);

        $historyFeatures = $data['history_features'] 
            ?? $data['historyFeatures'] 
            ?? $data['caracteristicas'] 
            ?? $data['features'] 
            ?? (is_array($rawHistoria) ? ($rawHistoria['caracteristicas'] ?? $rawHistoria['features'] ?? $rawHistoria['history_features'] ?? null) : null);

        $finalHistoryTitle = (!empty($historyTitle)) ? $historyTitle : ($settings->history_title ?: ($existingHistoria['titulo'] ?? ($existingHistoria['title'] ?? null)));
        $finalHistoryDesc = (!empty($historyDesc)) ? $historyDesc : ($settings->history_description ?: ($existingHistoria['descripcion'] ?? ($existingHistoria['description'] ?? null)));
        $finalHistoryYear = (!empty($historyYear)) ? (int)$historyYear : ($settings->history_year ?: ($existingHistoria['anio'] ?? ($existingHistoria['anioFundacion'] ?? null)));
        $finalHistoryImg = (!empty($historyImg)) ? $historyImg : ($settings->history_image ?: ($existingHistoria['fondo'] ?? ($existingHistoria['imagenFondo'] ?? null)));
        $finalHistoryFeatures = (!empty($historyFeatures) && is_array($historyFeatures)) ? $historyFeatures : ($settings->history_features ?: ($existingHistoria['caracteristicas'] ?? ($existingHistoria['features'] ?? null)));

        if (!empty($finalHistoryTitle)) {
            $updateData['history_title'] = $finalHistoryTitle;
        }
        if (!empty($finalHistoryDesc)) {
            $updateData['history_description'] = $finalHistoryDesc;
        }
        if (!empty($finalHistoryYear)) {
            $updateData['history_year'] = $finalHistoryYear;
        }
        if (!empty($finalHistoryImg)) {
            $updateData['history_image'] = $finalHistoryImg;
        }
        if (!empty($finalHistoryFeatures)) {
            $updateData['history_features'] = $finalHistoryFeatures;
        }

        if ($rawHistoria !== null || $historyTitle !== null || $historyDesc !== null || $historyYear !== null || $historyImg !== null || $historyFeatures !== null) {
            $mergedHistoria = is_array($rawHistoria) ? array_merge($existingHistoria, $rawHistoria) : $existingHistoria;

            if (!empty($finalHistoryTitle)) {
                $mergedHistoria['titulo'] = $finalHistoryTitle;
                $mergedHistoria['title'] = $finalHistoryTitle;
            }
            if (!empty($finalHistoryDesc)) {
                $mergedHistoria['descripcion'] = $finalHistoryDesc;
                $mergedHistoria['description'] = $finalHistoryDesc;
            }
            if (!empty($finalHistoryYear)) {
                $mergedHistoria['anio'] = $finalHistoryYear;
                $mergedHistoria['anioFundacion'] = $finalHistoryYear;
                $mergedHistoria['year'] = $finalHistoryYear;
            }
            if (!empty($finalHistoryImg)) {
                $mergedHistoria['fondo'] = $finalHistoryImg;
                $mergedHistoria['imagenFondo'] = $finalHistoryImg;
                $mergedHistoria['image'] = $finalHistoryImg;
            }
            if (!empty($finalHistoryFeatures)) {
                $mergedHistoria['caracteristicas'] = $finalHistoryFeatures;
                $mergedHistoria['features'] = $finalHistoryFeatures;
            }

            $updateData['historia_config'] = $mergedHistoria;
        }

        $settings->update($updateData);

        // Sincronizar columna is_featured en la tabla dishes si se enviaron platillos destacados
        if (isset($updateData['featured_dishes'])) {
            $dishIds = $updateData['featured_dishes'];
            if (!empty($dishIds)) {
                \App\Models\Dish::whereIn('id', $dishIds)->update(['is_featured' => true]);
                \App\Models\Dish::whereNotIn('id', $dishIds)->where('is_featured', true)->update(['is_featured' => false]);
            } else {
                \App\Models\Dish::where('is_featured', true)->update(['is_featured' => false]);
            }
        }

        // Destrucción de llaves de caché para forzar consulta limpia de base de datos
        Cache::forget('landing_featured_dishes');
        Cache::forget('landing_settings');
        Cache::forget('restaurant_settings');
        Cache::forget('landing_menu');
        Cache::forget('public_menu');

        // Sync with ConfiguracionGeneral
        $configuracion = \App\Models\ConfiguracionGeneral::first();
        if ($configuracion) {
            $configUpdates = [];
            if (isset($updateData['restaurant_name'])) $configUpdates['nombre_comercial'] = $updateData['restaurant_name'];
            if (isset($updateData['logo_url'])) $configUpdates['logotipo'] = $updateData['logo_url'];
            if (isset($updateData['brand_color'])) $configUpdates['color_primario'] = $updateData['brand_color'];
            if (isset($updateData['secondary_color'])) $configUpdates['color_apoyo'] = $updateData['secondary_color'];
            if (isset($data['fondo_sistema']) || isset($data['fondoSistema']) || isset($data['color_fondo'])) {
                $configUpdates['fondo_sistema'] = $data['fondo_sistema'] ?? $data['fondoSistema'] ?? $data['color_fondo'];
            }
            if (!empty($configUpdates)) {
                $configuracion->update($configUpdates);
            }
        }

        NotificationService::create('settings_updated', 'Configuración Guardada', 'Se actualizó la configuración general de la Landing Page');

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * PUT/POST /admin/settings/credentials
     */
    public function updateCredentials(UpdateAdminCredentialsRequest $request)
    {
        $user = $request->user() ?? auth()->user();

        if (!$user) {
            return response()->json([
                'error' => 'Usuario no autenticado'
            ], 401);
        }

        $validated = $request->validated();

        $user->email = $validated['email'] ?? $validated['admin_email'];
        if (isset($validated['phone']) || isset($validated['admin_phone'])) {
            $user->phone = $validated['phone'] ?? $validated['admin_phone'];
        }
        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }
        $user->using_default_credentials = false;
        $user->save();

        AuditLogger::log(
            'CREDENTIALS_UPDATED',
            'Configuración',
            "Credenciales administrativas actualizadas para " . $user->name,
            $user,
            'warning'
        );

        return response()->json([
            'message' => 'Credenciales administrativas actualizadas correctamente',
            'user'    => $user,
        ]);
    }

    /**
     * PUT/POST /admin/settings/payment-methods
     */
    public function updatePaymentMethods(UpdatePaymentMethodsRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $cash = (bool) ($validated['cash'] ?? $validated['acepta_efectivo'] ?? false);
        $card = (bool) ($validated['card'] ?? $validated['acepta_tarjeta'] ?? false);
        $transfer = (bool) ($validated['transfer'] ?? $validated['acepta_transferencia'] ?? false);

        $updateData = [
            'acepta_efectivo'      => $cash,
            'acepta_tarjeta'       => $card,
            'acepta_transferencia' => $transfer,
        ];

        if (array_key_exists('banco_nombre', $validated)) {
            $updateData['banco_nombre'] = $validated['banco_nombre'];
        } elseif (array_key_exists('bank_name', $validated)) {
            $updateData['banco_nombre'] = $validated['bank_name'];
        }

        if (array_key_exists('banco_clabe', $validated)) {
            $updateData['banco_clabe'] = $validated['banco_clabe'];
        } elseif (array_key_exists('bank_clabe', $validated)) {
            $updateData['banco_clabe'] = $validated['bank_clabe'];
        }

        if (array_key_exists('banco_titular', $validated)) {
            $updateData['banco_titular'] = $validated['banco_titular'];
        } elseif (array_key_exists('bank_account_holder', $validated)) {
            $updateData['banco_titular'] = $validated['bank_account_holder'];
        }

        $settings->update($updateData);

        AuditLogger::log(
            'PAYMENT_METHODS_UPDATED',
            'Configuración',
            "Métodos de pago actualizados por " . auth()->user()?->name,
            auth()->user(),
            'info'
        );

        try {
            $settingsPayload = $this->show()->getData(true);
            event(new \App\Events\LandingSettingsUpdated($settingsPayload));
            event(new \App\Events\LandingUpdated($settingsPayload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo LandingSettingsUpdated en updatePaymentMethods: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Métodos de pago actualizados correctamente',
            'settings' => $settings,
        ]);
    }

    /**
     * PUT/POST /admin/settings/delivery-zone
     */
    public function updateDeliveryZone(UpdateDeliveryZoneRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $city = trim($validated['city'] ?? $validated['ciudad'] ?? '');
        $state = trim($validated['state'] ?? $validated['estado'] ?? '');
        $municipality = trim($validated['municipality'] ?? $validated['municipio'] ?? '');
        $street = trim($validated['street'] ?? $validated['street_name'] ?? $validated['calle'] ?? '');
        $zipCode = trim($validated['zip_code'] ?? $validated['postal_code'] ?? $validated['codigo_postal'] ?? '');
        $radiusKm = (float) ($validated['delivery_radius_km'] ?? 10.0);
        $radiusMeters = (int) round($radiusKm * 1000);

        $combinedCityState = ($city && $state) ? "{$city}, {$state}" : ($city ?: $state);

        $updateData = [
            'city_state'             => $combinedCityState,
            'municipio'              => $municipality,
            'street_name'            => $street,
            'address'                => $street,
            'postal_code'            => $zipCode,
            'delivery_radius_km'     => $radiusKm,
            'delivery_radius_meters' => $radiusMeters,
        ];

        if (isset($validated['latitude'])) {
            $updateData['latitude'] = (float) $validated['latitude'];
        }
        if (isset($validated['longitude'])) {
            $updateData['longitude'] = (float) $validated['longitude'];
        }
        if (isset($validated['coverage_polygon'])) {
            $updateData['coverage_polygon'] = $validated['coverage_polygon'];
        }

        $settings->update($updateData);

        AuditLogger::log(
            'DELIVERY_ZONE_UPDATED',
            'Configuración',
            "Zona de entrega actualizada por " . auth()->user()?->name,
            auth()->user(),
            'info'
        );

        try {
            $settingsPayload = $this->show()->getData(true);
            event(new \App\Events\LandingSettingsUpdated($settingsPayload));
            event(new \App\Events\LandingUpdated($settingsPayload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo LandingSettingsUpdated en updateDeliveryZone: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Zona de entrega actualizada correctamente',
            'settings' => $settings,
        ]);
    }

    /**
     * PUT/POST /admin/settings/landing
     * PUT/POST /settings/landing
     */
    public function updateLandingPage(UpdateLandingPageRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $updateData = [];

        // 1. Textos Principales y Hero
        if (isset($validated['hero_title'])) {
            $updateData['hero_title'] = $validated['hero_title'];
        }
        if (array_key_exists('hero_slogan', $validated)) {
            $updateData['hero_slogan'] = $validated['hero_slogan'];
            $updateData['hero_description'] = $validated['hero_slogan'];
        }

        // 2. Imagen de Fondo de Portada (Hero Background Image)
        if ($request->hasFile('hero_background_image')) {
            $file = $request->file('hero_background_image');
            $compressed = ImageCompressionService::compressAndStore($file, 'landing', 1600, 75, 'hero_');
            $url = url($compressed['url']);
            $updateData['hero_image'] = $url;
            $updateData['hero_image_url'] = $url;
        } elseif (!empty($validated['hero_background_image']) && is_string($validated['hero_background_image'])) {
            $updateData['hero_image'] = $validated['hero_background_image'];
            $updateData['hero_image_url'] = $validated['hero_background_image'];
        }

        // 3. Carrusel
        if (array_key_exists('enable_carousel', $validated)) {
            $updateData['use_carousel'] = (bool) $validated['enable_carousel'];
        }

        if (isset($validated['carousel_images']) && is_array($validated['carousel_images'])) {
            $carouselUrls = [];
            foreach ($validated['carousel_images'] as $idx => $item) {
                if ($item instanceof \Illuminate\Http\UploadedFile) {
                    $compressed = ImageCompressionService::compressAndStore($item, 'landing', 1600, 75, "carousel_{$idx}_");
                    $carouselUrls[] = url($compressed['url']);
                } elseif (is_string($item) && !empty($item)) {
                    $carouselUrls[] = $item;
                }
            }
            $updateData['banner_images'] = $carouselUrls;
        }

        // 4. Sección Historia
        $historyImageUrl = null;
        if ($request->hasFile('history_background_image')) {
            $file = $request->file('history_background_image');
            $compressed = ImageCompressionService::compressAndStore($file, 'landing', 1600, 75, 'history_');
            $historyImageUrl = url($compressed['url']);
            $updateData['history_image'] = $historyImageUrl;
        } elseif (!empty($validated['history_background_image']) && is_string($validated['history_background_image'])) {
            $historyImageUrl = $validated['history_background_image'];
            $updateData['history_image'] = $historyImageUrl;
        }

        if (isset($validated['history_title'])) {
            $updateData['history_title'] = $validated['history_title'];
        }
        if (isset($validated['history_description'])) {
            $updateData['history_description'] = $validated['history_description'];
        }
        if (isset($validated['foundation_year'])) {
            $updateData['history_year'] = (int) $validated['foundation_year'];
        }

        // 5. Características (Features)
        if (isset($validated['features']) && is_array($validated['features'])) {
            $updateData['history_features'] = $validated['features'];
        }

        // 6. Sincronizar historia_config (JSON persistido)
        $existingHistoria = is_array($settings->historia_config) ? $settings->historia_config : [];
        $mergedHistoria = array_merge($existingHistoria, [
            'titulo'          => $updateData['history_title'] ?? ($existingHistoria['titulo'] ?? ''),
            'title'           => $updateData['history_title'] ?? ($existingHistoria['title'] ?? ''),
            'descripcion'     => $updateData['history_description'] ?? ($existingHistoria['descripcion'] ?? ''),
            'description'     => $updateData['history_description'] ?? ($existingHistoria['description'] ?? ''),
            'anio'            => $updateData['history_year'] ?? ($existingHistoria['anio'] ?? null),
            'anioFundacion'   => $updateData['history_year'] ?? ($existingHistoria['anioFundacion'] ?? null),
            'year'            => $updateData['history_year'] ?? ($existingHistoria['year'] ?? null),
            'fondo'           => $historyImageUrl ?? ($updateData['history_image'] ?? ($existingHistoria['fondo'] ?? null)),
            'imagenFondo'     => $historyImageUrl ?? ($updateData['history_image'] ?? ($existingHistoria['imagenFondo'] ?? null)),
            'image'           => $historyImageUrl ?? ($updateData['history_image'] ?? ($existingHistoria['image'] ?? null)),
            'caracteristicas' => $updateData['history_features'] ?? ($existingHistoria['caracteristicas'] ?? []),
            'features'        => $updateData['history_features'] ?? ($existingHistoria['features'] ?? []),
        ]);
        $updateData['historia_config'] = $mergedHistoria;

        $settings->update($updateData);

        AuditLogger::log(
            'LANDING_SETTINGS_UPDATED',
            'Configuración',
            "Configuración de Landing Page actualizada por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'landing_settings_updated',
            'Landing Page Actualizada',
            'Se actualizó la configuración de la Landing Page con éxito'
        );

        // Destrucción de llaves de caché
        Cache::forget('landing_featured_dishes');
        Cache::forget('landing_settings');
        Cache::forget('restaurant_settings');
        Cache::forget('landing_menu');
        Cache::forget('public_menu');

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updateLandingPage: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Configuración de la Landing Page actualizada correctamente',
            'settings' => $payload,
        ]);
    }

    /**
     * PUT/POST /admin/settings/featured-dishes
     * PUT/POST /settings/featured-dishes
     */
    public function updateFeaturedDishes(UpdateFeaturedDishesRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $updateData = [];

        if (array_key_exists('subtitle', $validated)) {
            $updateData['menu_subtitle'] = $validated['subtitle'];
        }
        if (isset($validated['title'])) {
            $updateData['menu_title'] = $validated['title'];
        }
        if (array_key_exists('button_text', $validated)) {
            $updateData['cta_menu_text'] = $validated['button_text'];
        }

        $catIds = array_values(array_unique(array_filter(array_map('intval', $validated['featured_categories'] ?? []))));
        $dishIds = array_values(array_unique(array_filter(array_map('intval', $validated['featured_dishes'] ?? []))));

        $updateData['featured_categories'] = $catIds;
        $updateData['featured_dishes'] = $dishIds;

        $existingPlatillos = is_array($settings->platillos_seccion) ? $settings->platillos_seccion : [];
        $mergedPlatillos = array_merge($existingPlatillos, [
            'labelSuperior'       => $validated['subtitle'] ?? ($existingPlatillos['labelSuperior'] ?? ''),
            'subtitle'            => $validated['subtitle'] ?? ($existingPlatillos['subtitle'] ?? ''),
            'tituloPrincipal'     => $validated['title'] ?? ($existingPlatillos['tituloPrincipal'] ?? ''),
            'title'               => $validated['title'] ?? ($existingPlatillos['title'] ?? ''),
            'textoDebajoBoton'    => $validated['button_text'] ?? ($existingPlatillos['textoDebajoBoton'] ?? ''),
            'button_text'         => $validated['button_text'] ?? ($existingPlatillos['button_text'] ?? ''),
            'selected_categories' => $catIds,
            'featured_categories' => $catIds,
            'selected_dishes'     => $dishIds,
            'featured_dishes'     => $dishIds,
        ]);
        $updateData['platillos_seccion'] = $mergedPlatillos;

        $settings->update($updateData);

        // Sincronizar columna is_featured en la tabla dishes
        if (!empty($dishIds)) {
            \App\Models\Dish::whereIn('id', $dishIds)->update(['is_featured' => true]);
            \App\Models\Dish::whereNotIn('id', $dishIds)->where('is_featured', true)->update(['is_featured' => false]);
        } else {
            \App\Models\Dish::where('is_featured', true)->update(['is_featured' => false]);
        }

        // Destrucción de llaves de caché para forzar consulta limpia de base de datos
        Cache::forget('landing_featured_dishes');
        Cache::forget('landing_settings');
        Cache::forget('restaurant_settings');
        Cache::forget('landing_menu');
        Cache::forget('public_menu');

        AuditLogger::log(
            'FEATURED_DISHES_UPDATED',
            'Configuración',
            "Platillos y categorías destacados actualizados por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'featured_dishes_updated',
            'Platillos Destacados Actualizados',
            'Se actualizó la sección de platillos destacados con éxito'
        );

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updateFeaturedDishes: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Platillos destacados actualizados correctamente',
            'settings' => $payload,
        ]);
    }

    /**
     * PUT/POST /admin/settings/exclusive-services
     * PUT/POST /settings/exclusive-services
     */
    public function updateExclusiveServices(UpdateExclusiveServicesRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $settings->update([
            'servicios_config' => $validated['services'],
        ]);

        AuditLogger::log(
            'EXCLUSIVE_SERVICES_UPDATED',
            'Configuración',
            "Servicios exclusivos actualizados por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'exclusive_services_updated',
            'Servicios Exclusivos Actualizados',
            'Se actualizaron los 3 servicios exclusivos con éxito'
        );

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updateExclusiveServices: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Servicios exclusivos actualizados correctamente',
            'settings' => $payload,
        ]);
    }

    /**
     * PUT/POST /admin/settings/promo-banner
     * PUT/POST /settings/promo-banner
     */
    public function updatePromoBanner(UpdatePromoBannerRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();
        $isActive = (bool) $validated['is_active'];

        if ($isActive) {
            $bannerData = [
                'activo'              => true,
                'is_active'           => true,
                'porcentaje'          => (int) $validated['discount_percentage'],
                'discount_percentage' => (int) $validated['discount_percentage'],
                'badgeVigencia'       => $validated['validity_badge'],
                'validity_badge'      => $validated['validity_badge'],
                'tituloDescuento'     => $validated['title'],
                'title'               => $validated['title'],
                'descripcion'         => $validated['description'],
                'description'         => $validated['description'],
                'textoBoton'          => $validated['button_text'],
                'button_text'         => $validated['button_text'],
                'textoBotonSub'       => $validated['button_subtext'],
                'button_subtext'      => $validated['button_subtext'],
            ];
        } else {
            // Si is_active es false, ignorar deliberadamente cualquier texto o porcentaje y guardar null
            $bannerData = [
                'activo'              => false,
                'is_active'           => false,
                'porcentaje'          => null,
                'discount_percentage' => null,
                'badgeVigencia'       => null,
                'validity_badge'      => null,
                'tituloDescuento'     => null,
                'title'               => null,
                'descripcion'         => null,
                'description'         => null,
                'textoBoton'          => null,
                'button_text'         => null,
                'textoBotonSub'       => null,
                'button_subtext'      => null,
            ];
        }

        $settings->update([
            'banner_descuento' => $bannerData,
        ]);

        AuditLogger::log(
            'PROMO_BANNER_UPDATED',
            'Configuración',
            "Banner promocional de landing " . ($isActive ? 'activado' : 'desactivado') . " por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'promo_banner_updated',
            'Banner Promocional Actualizado',
            $isActive ? 'Se activó el banner de descuento en la Landing Page' : 'Se desactivó el banner de descuento en la Landing Page'
        );

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updatePromoBanner: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Banner promocional actualizado correctamente',
            'settings' => $payload,
        ]);
    }

    /**
     * PUT/POST /admin/settings/landing-reservations
     * PUT/POST /settings/landing-reservations
     * PUT/POST /admin/settings/reservations-section
     * PUT/POST /settings/reservations-section
     */
    public function updateLandingReservations(UpdateLandingReservationsRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $reservationsData = [
            'title'            => $validated['title'],
            'tituloPrincipal'  => $validated['title'],
            'titulo'           => $validated['title'],
            'subtitle'         => $validated['subtitle'],
            'subtituloDorado'  => $validated['subtitle'],
            'subtitulo'        => $validated['subtitle'],
            'description'      => $validated['description'],
            'textoDescriptivo' => $validated['description'],
            'descripcion'      => $validated['description'],
            'weekday_start'    => $validated['weekday_start'],
            'weekday_end'      => $validated['weekday_end'],
            'weekend_start'    => $validated['weekend_start'],
            'weekend_end'      => $validated['weekend_end'],
            'horarios'         => [
                'weekday_start'            => $validated['weekday_start'],
                'weekday_end'              => $validated['weekday_end'],
                'weekend_start'            => $validated['weekend_start'],
                'weekend_end'              => $validated['weekend_end'],
                'lunesViernesInicio'       => $validated['weekday_start'],
                'lunesViernesFin'          => $validated['weekday_end'],
                'sabadoDomingoInicio'      => $validated['weekend_start'],
                'sabadoDomingoFin'         => $validated['weekend_end'],
                'lunes_viernes_inicio_24h' => $validated['weekday_start'],
                'lunes_viernes_fin_24h'    => $validated['weekday_end'],
                'sabado_domingo_inicio_24h'=> $validated['weekend_start'],
                'sabado_domingo_fin_24h'   => $validated['weekend_end'],
            ],
            'policies'         => array_values($validated['policies']),
            'politicas'        => array_values($validated['policies']),
        ];

        $settings->update([
            'reservaciones_seccion' => $reservationsData,
        ]);

        AuditLogger::log(
            'LANDING_RESERVATIONS_UPDATED',
            'Configuración',
            "Sección de reservaciones de landing actualizada por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'landing_reservations_updated',
            'Reservaciones de Landing Actualizadas',
            'Se actualizó la sección de reservaciones de la Landing Page con éxito'
        );

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updateLandingReservations: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Sección de reservaciones actualizada correctamente',
            'settings' => $payload,
        ]);
    }

    /**
     * PUT/POST /admin/settings/landing-contact
     * PUT/POST /settings/landing-contact
     * PUT/POST /admin/settings/contact-section
     * PUT/POST /settings/contact-section
     */
    public function updateLandingContact(UpdateLandingContactRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        $cleanPhone = $validated['public_phone'];
        $cleanWhatsapp = $validated['whatsapp_number'];
        $cleanEmail = $validated['contact_email'];
        $mapsLink = $validated['google_maps_url'];
        $title = $validated['title'];
        $subtitle = $validated['subtitle'];
        $eventsText = $validated['events_text'] ?? '';
        $instagram = $validated['instagram'] ?? '';
        $facebook = $validated['facebook'] ?? '';
        $tiktok = $validated['tiktok'] ?? '';

        $whatsappUrl = 'https://wa.me/' . (preg_replace('/[^0-9]/', '', $cleanWhatsapp) ?: '527440000000');

        $contactoSeccionData = [
            'titulo'           => $title,
            'title'            => $title,
            'labelSuperior'    => $subtitle,
            'subtitle'         => $subtitle,
            'textoEventos'     => $eventsText,
            'events_text'      => $eventsText,
            'mapsLink'         => $mapsLink,
            'google_maps_url'  => $mapsLink,
            'telefono'         => $cleanPhone,
            'phone'            => $cleanPhone,
            'public_phone'     => $cleanPhone,
            'email'            => $cleanEmail,
            'contact_email'    => $cleanEmail,
            'correo'           => $cleanEmail,
            'correo_contacto'  => $cleanEmail,
            'whatsapp'         => $cleanWhatsapp,
            'numeroWhatsapp'   => $cleanWhatsapp,
            'numero_whatsapp'  => $cleanWhatsapp,
            'whatsapp_number'  => $cleanWhatsapp,
            'redesSociales'    => [
                'instagram' => $instagram,
                'facebook'  => $facebook,
                'tiktok'    => $tiktok,
            ],
            'instagram'        => $instagram,
            'facebook'         => $facebook,
            'tiktok'           => $tiktok,
        ];

        $updateData = [
            'contacto_seccion'      => $contactoSeccionData,
            'contact_phone'         => $cleanPhone,
            'phone'                 => $cleanPhone,
            'telefono'              => $cleanPhone,
            'telefono_publico'      => $cleanPhone,
            'contact_email'         => $cleanEmail,
            'email'                 => $cleanEmail,
            'correo'                => $cleanEmail,
            'correo_contacto'       => $cleanEmail,
            'contact_whatsapp'      => $cleanWhatsapp,
            'whatsapp'              => $cleanWhatsapp,
            'numero_whatsapp'       => $cleanWhatsapp,
            'contact_whatsapp_url'  => $whatsappUrl,
            'instagram_url'         => $instagram,
            'instagram'             => $instagram,
            'facebook_url'          => $facebook,
            'facebook'              => $facebook,
            'tiktok_url'            => $tiktok,
            'tiktok'                => $tiktok,
        ];

        $settings->update($updateData);

        AuditLogger::log(
            'LANDING_CONTACT_UPDATED',
            'Configuración',
            "Sección de contacto de landing actualizada por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'landing_contact_updated',
            'Contacto de Landing Actualizado',
            'Se actualizó la sección de contacto de la Landing Page con éxito'
        );

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updateLandingContact: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Sección de contacto actualizada correctamente',
            'settings' => $payload,
        ]);
    }

    /**
     * PUT/POST /admin/settings/landing-delivery
     * PUT/POST /settings/landing-delivery
     * PUT/POST /admin/settings/delivery-section
     * PUT/POST /settings/delivery-section
     */
    public function updateLandingDelivery(UpdateLandingDeliveryRequest $request)
    {
        $settings = RestaurantSetting::firstOrCreate([]);
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $compressed = ImageCompressionService::compressAndStore($file, 'landing', 1200, 75, 'delivery_');
            $imageUrl = $compressed['url'];
        } else {
            $imageUrl = $validated['image'] ?? ($settings->delivery_image_url ?: '/delivery.jpg');
        }

        $imageTitle = $validated['image_title'] ?? '';
        $imageAlt = $validated['image_alt'] ?? '';
        $whatsappNumber = $validated['whatsapp_number'];

        $deliveryData = [
            'subtitle'                   => $validated['subtitle'],
            'labelSuperior'              => $validated['subtitle'],
            'title'                      => $validated['title'],
            'tituloPrincipal'            => $validated['title'],
            'description'                => $validated['description'],
            'descripcion'                => $validated['description'],
            'image'                      => $imageUrl,
            'imagen'                     => $imageUrl,
            'imagen_url'                 => $imageUrl,
            'delivery_image_url'         => $imageUrl,
            'image_title'                => $imageTitle,
            'imagenTitulo'               => $imageTitle,
            'delivery_image_title'       => $imageTitle,
            'image_alt'                  => $imageAlt,
            'imagenDescripcion'          => $imageAlt,
            'delivery_image_description' => $imageAlt,
            'whatsapp_number'            => $whatsappNumber,
            'numeroWhatsapp'             => $whatsappNumber,
            'button_subtext'             => $validated['button_subtext'],
            'textoBoton'                 => $validated['button_subtext'],
            'benefits'                   => array_values($validated['benefits']),
            'beneficios'                 => array_values($validated['benefits']),
            'steps'                      => array_values($validated['steps']),
            'pasos'                      => array_values($validated['steps']),
            'guarantees'                 => array_values($validated['guarantees']),
            'garantias'                  => array_values($validated['guarantees']),
        ];

        $settings->update([
            'delivery_seccion'           => $deliveryData,
            'delivery_title'             => $validated['title'],
            'delivery_description'       => $validated['description'],
            'delivery_image_url'         => $imageUrl,
            'delivery_image_title'       => $imageTitle,
            'delivery_image_description' => $imageAlt,
        ]);

        AuditLogger::log(
            'LANDING_DELIVERY_UPDATED',
            'Configuración',
            "Sección de delivery de landing actualizada por " . (auth()->user()?->name ?? 'Administrador'),
            auth()->user(),
            'info'
        );

        NotificationService::create(
            'landing_delivery_updated',
            'Delivery de Landing Actualizado',
            'Se actualizó la sección de delivery de la Landing Page con éxito'
        );

        $response = $this->show();
        $payload = $response->getData(true);

        try {
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket LandingSettingsUpdated en updateLandingDelivery: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Sección de delivery actualizada correctamente',
            'settings' => $payload,
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp,svg,gif|max:25600'
        ]);

        $settings = RestaurantSetting::firstOrCreate([]);

        $file = $request->file('logo');
        $compressed = ImageCompressionService::compressAndStore($file, 'logos', 800, 75, 'logo_');
        $url = asset($compressed['url']);
        
        $settings->update(['logo_url' => $url]);

        $configuracion = \App\Models\ConfiguracionGeneral::first();
        if ($configuracion) {
            $configuracion->update(['logotipo' => $url]);
        }

        try {
            $payload = $this->show()->getData(true);
            event(new \App\Events\LandingSettingsUpdated($payload));
            event(new \App\Events\LandingUpdated($payload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo LandingSettingsUpdated en uploadLogo: ' . $e->getMessage());
        }

        @unlink(public_path('storage/logos/favicon_optimized.png'));

        return response()->json([
            'logo_url' => $url,
            'message'  => 'Logo actualizado correctamente'
        ]);
    }

    public function getFavicon()
    {
        $settings = RestaurantSetting::first();
        $logoUrl = $settings->logo_url ?? null;

        if (!$logoUrl) {
            $configuracion = \App\Models\ConfiguracionGeneral::first();
            $logoUrl = $configuracion->logotipo ?? null;
        }

        $filePath = null;
        if ($logoUrl) {
            $parsedPath = parse_url($logoUrl, PHP_URL_PATH);
            $cleanPath = ltrim($parsedPath, '/');
            if (str_starts_with($cleanPath, 'storage/')) {
                $filePath = public_path($cleanPath);
                if (!file_exists($filePath)) {
                    $filePath = storage_path('app/public/' . substr($cleanPath, 8));
                }
            } else {
                $filePath = public_path('storage/' . $cleanPath);
            }
        }

        if (!$filePath || !file_exists($filePath)) {
            return response()->noContent(404);
        }

        $info = @getimagesize($filePath);
        $mime = $info['mime'] ?? 'image/png';

        return response()->file($filePath, [
            'Content-Type' => $mime,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function getCPByCiudad(Request $request)
    {
        $ciudad = $request->query('ciudad');
        $estado = $request->query('estado');

        if (!$ciudad || !$estado) {
            return response()->json([]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http
                ::timeout(5)
                ->get(
                    'https://sepomex.razektheone.com/municipios',
                    ['municipio' => $ciudad,
                     'estado' => $estado]
                );

            if ($response->failed()) {
                return response()->json([]);
            }

            $data = $response->json();
            $cps = collect($data['municipios'] ?? [])
                ->pluck('codigo_postal')
                ->filter()
                ->unique()
                ->sort()
                ->values();

            return response()->json($cps);

        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function getCallesByCP(Request $request)
    {
        $cp = $request->query('cp');

        if (!$cp || strlen($cp) !== 5) {
            return response()->json([]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http
                ::timeout(5)
                ->get(
                    'https://sepomex.razektheone.com/codigo_postal',
                    ['cp' => $cp]
                );

            if ($response->failed()) {
                return response()->json([]);
            }

            $data = $response->json();
            if ($data['error'] ?? true) {
                return response()->json([]);
            }

            // SEPOMEX no tiene calles, retornar
            // municipio y estado como referencia
            $info = $data['codigo_postal'] ?? [];
            return response()->json([
                'municipio' => $info['municipio'] ?? '',
                'estado'    => $info['estado'] ?? '',
                'colonias'  => collect(
                    $info['colonias'] ?? []
                )->pluck('colonia')->values()
            ]);

        } catch (\Exception $e) {
            return response()->json([]);
        }
    }
}
