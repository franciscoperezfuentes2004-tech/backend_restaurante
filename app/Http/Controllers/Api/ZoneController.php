<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index()
    {
        return response()->json(DeliveryZone::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'shipping_cost' => 'required|numeric|min:0',
            'active'        => 'boolean',
        ]);

        $zone = DeliveryZone::create($data);

        return response()->json($zone, 201);
    }

    public function update(Request $request, DeliveryZone $zone)
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'shipping_cost' => 'sometimes|numeric|min:0',
            'active'        => 'boolean',
        ]);

        $zone->update($data);

        return response()->json($zone);
    }

    public function destroy(DeliveryZone $zone)
    {
        $zone->delete();
        return response()->json(['message' => 'Zona de entrega eliminada']);
    }
}
