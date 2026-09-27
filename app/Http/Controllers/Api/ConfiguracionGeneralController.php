<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Models\ConfiguracionGeneral;
use App\Services\AuditLogger;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionGeneralController extends Controller
{
    /**
     * GET /api/admin/configuracion
     * Devuelve el registro único de configuración (creándolo con defaults si no existe).
     */
    public function show()
    {
        $config = ConfiguracionGeneral::instance();
        return response()->json($config);
    }

    /**
     * PUT /api/admin/configuracion
     * Guarda todos los campos del contenedor de configuración general.
     */
    public function update(UpdateGeneralSettingsRequest $request)
    {
        $config = ConfiguracionGeneral::instance();
        $validated = $request->validated();

        $isDeliveryActive = (bool) ($validated['is_delivery_active'] ?? $validated['delivery_activo'] ?? false);

        // LÓGICA DE NEGOCIO: Si is_delivery_active llega como false, el controlador debe ignorar los valores
        // numéricos enviados para esos campos e inyectar 0 o null en la base de datos PostgreSQL.
        $costoEnvioFijo = $isDeliveryActive
            ? (float) ($validated['fixed_delivery_fee'] ?? $validated['costo_envio_fijo'] ?? 0)
            : 0.0;

        $envioGratisDesde = $isDeliveryActive
            ? (float) ($validated['free_delivery_threshold'] ?? $validated['envio_gratis_desde'] ?? 0)
            : 0.0;

        $theme = $validated['theme'] ?? null;
        if ($theme) {
            $modoFondo = ($theme === 'dark' || $theme === 'oscuro') ? 'oscuro' : 'claro';
        } else {
            $modoFondo = $validated['modo_fondo'] ?? $config->modo_fondo;
        }
        $fondoSistema = $validated['fondo_sistema'] ?? ($modoFondo === 'oscuro' ? '#1C1917' : '#FFFFFF');

        $data = [
            'nombre_comercial'   => $validated['business_name'] ?? $validated['nombre_comercial'] ?? $config->nombre_comercial,
            'fondo_sistema'      => $fondoSistema,
            'modo_fondo'         => $modoFondo,
            'color_primario'     => $validated['primary_color'] ?? $validated['color_primario'] ?? $config->color_primario,
            'color_apoyo'        => $validated['color_apoyo'] ?? $config->color_apoyo,
            'color_apoyo_activo' => isset($validated['color_apoyo_activo']) ? (bool) $validated['color_apoyo_activo'] : $config->color_apoyo_activo,
            'delivery_activo'    => $isDeliveryActive,
            'costo_envio_fijo'   => $costoEnvioFijo,
            'envio_gratis_desde' => $envioGratisDesde,
        ];

        // Manejar subida de archivo de logotipo si se envió en la petición
        $logoFile = $request->file('logo') ?? $request->file('logotipo');
        if ($logoFile) {
            ImageCompressionService::deleteOldImage($config->logotipo, 'logos');
            $compressed = ImageCompressionService::compressAndStore($logoFile, 'logos', 800, 75, 'logo_');
            $data['logotipo'] = $compressed['url'];
        }

        $config->update($data);

        AuditLogger::log(
            'CONFIG_UPDATED',
            'Configuración',
            "Configuración general actualizada por " . auth()->user()?->name,
            auth()->user(),
            'info'
        );

        try {
            $settingsPayload = (new SettingsController())->show()->getData(true);
            event(new \App\Events\LandingSettingsUpdated($settingsPayload));
            event(new \App\Events\LandingUpdated($settingsPayload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo LandingSettingsUpdated desde ConfiguracionGeneralController: ' . $e->getMessage());
        }

        return response()->json($config);
    }

    /**
     * POST /api/admin/configuracion/logotipo
     * Sube el archivo de logotipo a storage/app/public/logos y guarda la ruta en logotipo.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logotipo' => 'required_without_all:logo,file,image|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'logo'     => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'file'     => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'image'    => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
        ]);

        $file = $request->file('logotipo') ?? $request->file('logo') ?? $request->file('file') ?? $request->file('image');

        if (!$file) {
            return response()->json(['message' => 'No se proporcionó ningún archivo de logotipo válido.'], 400);
        }

        $config = ConfiguracionGeneral::instance();
        ImageCompressionService::deleteOldImage($config->logotipo, 'logos');
        $compressed = ImageCompressionService::compressAndStore($file, 'logos', 800, 75, 'logo_');
        $publicUrl = $compressed['url'];

        $config->update([
            'logotipo' => $publicUrl
        ]);

        AuditLogger::log(
            'LOGO_UPLOADED',
            'Configuración',
            "Logotipo de la empresa actualizado por " . auth()->user()?->name,
            auth()->user(),
            'info'
        );

        try {
            $settingsPayload = (new SettingsController())->show()->getData(true);
            event(new \App\Events\LandingSettingsUpdated($settingsPayload));
            event(new \App\Events\LandingUpdated($settingsPayload));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo LandingSettingsUpdated en uploadLogo: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Logotipo subido y guardado exitosamente.',
            'logotipo' => $publicUrl,
            'url'      => $publicUrl,
            'config'   => $config
        ]);
    }
}
