<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtraRequest;
use App\Http\Requests\UpdateExtraRequest;
use App\Models\Extra;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ExtraController extends Controller
{
    private function formatExtra(Extra $extra): array
    {
        return [
            'id'      => $extra->id,
            'name'    => $extra->name,
            'price'   => (float) ($extra->is_free ? 0 : $extra->price),
            'is_free' => (bool) $extra->is_free,
        ];
    }

    public function index()
    {
        $extras = Extra::orderBy('name', 'asc')->get();
        $mapped = $extras->map(fn($extra) => $this->formatExtra($extra));

        return response()->json(['extras' => $mapped]);
    }

    public function store(StoreExtraRequest $request)
    {
        $data = $request->validated();

        $isFree = (bool) $request->is_free;
        $data['is_free'] = $isFree;

        // Sanitización estricta: si is_free es true, sobreescribir price = 0
        if ($isFree) {
            $data['price'] = 0.0;
            $request->merge(['price' => 0]);
        } else {
            $data['price'] = (float) ($data['price'] ?? 0.0);
        }

        $extra = Extra::create($data);

        NotificationService::create(
            'extra_created',
            'Extra Creado',
            "Se creó el complemento extra '{$extra->name}'",
            ['extra_id' => $extra->id]
        );

        AuditLogger::log('EXTRA_CREATED', 'Extras', "Extra '{$extra->name}' creado", auth()->user(), 'info');

        return response()->json($this->formatExtra($extra), 201);
    }

    public function show($id)
    {
        $extra = Extra::findOrFail($id);
        return response()->json($this->formatExtra($extra));
    }

    public function update(UpdateExtraRequest $request, $id)
    {
        $extra = Extra::findOrFail($id);

        $data = $request->validated();

        $isFree = $request->has('is_free')
            ? (bool) $request->is_free
            : (bool) $extra->is_free;

        $data['is_free'] = $isFree;

        // Sanitización estricta: si $request->is_free es true, el backend debe sobreescribir $request->price = 0 ignorando cualquier número enviado
        if ($isFree) {
            $data['price'] = 0.0;
            $request->merge(['price' => 0]);
        } elseif (isset($data['price'])) {
            $data['price'] = (float) $data['price'];
        }

        $extra->update($data);

        NotificationService::create(
            'extra_updated',
            'Extra Editado',
            "Se actualizó el complemento extra '{$extra->name}'",
            ['extra_id' => $extra->id]
        );

        AuditLogger::log('EXTRA_UPDATED', 'Extras', "Extra '{$extra->name}' actualizado", auth()->user(), 'info');

        return response()->json($this->formatExtra($extra));
    }

    public function destroy($id)
    {
        $extra = Extra::findOrFail($id);

        if ($extra->dishes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un extra asignado a platillos'
            ], 422);
        }

        $name = $extra->name;
        $extra->delete();

        NotificationService::create(
            'extra_deleted',
            'Extra Eliminado',
            "Se eliminó el complemento extra '{$name}'",
            ['extra_id' => $id]
        );

        AuditLogger::log('EXTRA_DELETED', 'Extras', "Extra '{$name}' eliminado", auth()->user(), 'warning');

        return response()->json(['message' => 'Extra eliminado correctamente']);
    }
}
