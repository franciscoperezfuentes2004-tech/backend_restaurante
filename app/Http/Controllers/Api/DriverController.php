<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryDriver::query();

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status') && !in_array($request->status, ['all', 'todos', 'todas'], true)) {
            $isActive = in_array($request->status, ['active', 'activo', 'activos'], true);
            $query->where('active', $isActive);
        }

        $drivers = $query->orderBy('name')->get()->map(function ($drv) {
            return [
                'id'     => $drv->id,
                'name'   => $drv->name,
                'phone'  => $drv->phone ?: '',
                'email'  => $drv->email ?: ($drv->user ? $drv->user->email : ''),
                'active' => (bool) $drv->active,
            ];
        });

        $allDrivers = DeliveryDriver::all();
        $total = $allDrivers->count();
        $activos = $allDrivers->where('active', true)->count();
        $inactivos = $allDrivers->where('active', false)->count();

        return response()->json([
            'drivers' => $drivers,
            'resumen' => [
                'total'     => $total,
                'activos'   => $activos,
                'inactivos' => $inactivos,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'phone'    => 'required|string|max:20',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'active'   => 'nullable|boolean',
        ]);

        $isActive = isset($data['active']) ? (bool) $data['active'] : true;

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'repartidor',
        ]);

        $driver = DeliveryDriver::create([
            'name'    => $data['name'],
            'phone'   => $data['phone'],
            'email'   => $data['email'],
            'user_id' => $user->id,
            'active'  => $isActive,
            'status'  => $isActive ? 'active' : 'inactive',
        ]);

        return response()->json([
            'id'     => $driver->id,
            'name'   => $driver->name,
            'phone'  => $driver->phone,
            'email'  => $driver->email,
            'active' => (bool) $driver->active,
        ], 201);
    }

    public function show($id)
    {
        $driver = DeliveryDriver::findOrFail($id);
        return response()->json([
            'id'     => $driver->id,
            'name'   => $driver->name,
            'phone'  => $driver->phone,
            'email'  => $driver->email ?: ($driver->user ? $driver->user->email : ''),
            'active' => (bool) $driver->active,
        ]);
    }

    public function update(Request $request, $id)
    {
        $driver = DeliveryDriver::find($id);
        $user = null;

        if ($driver) {
            $userId = $driver->user_id;
            $user = $driver->user ?: User::find($userId);
        } else {
            $user = User::find($id);
            $userId = $user ? $user->id : $id;
            $driver = $user ? DeliveryDriver::where('user_id', $user->id)->first() : null;
        }

        // Normalizar nombres de atributos si vienen en español o inglés
        $nombre = $request->input('nombre', $request->input('name'));
        $telefono = $request->input('telefono', $request->input('phone'));
        $estado = $request->input('estado', $request->input('is_active', $request->input('active', $request->input('status') === 'active' ? true : ($request->input('status') === 'inactive' ? false : null))));
        $email = $request->input('email', $request->input('correo'));

        $cleanNombre = is_string($nombre) ? preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $nombre) : $nombre;
        $cleanNombre = is_string($cleanNombre) ? trim(strip_tags($cleanNombre)) : $cleanNombre;
        $cleanTelefono = is_string($telefono) ? preg_replace('/[^0-9]/', '', trim((string)$telefono)) : $telefono;
        $cleanEmail = is_string($email) ? trim(strip_tags((string)$email)) : $email;

        $request->merge([
            'nombre'   => $cleanNombre,
            'name'     => $cleanNombre,
            'telefono' => $cleanTelefono,
            'phone'    => $cleanTelefono,
            'estado'   => $estado,
            'is_active'=> $estado,
            'active'   => $estado,
            'email'    => $cleanEmail,
        ]);

        $request->validate([
            'nombre'   => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'telefono' => ['required', 'digits:10'],
            'estado'   => ['required', 'boolean'],
            
            // CRÍTICO: Excluir el ID actual de la validación de unicidad
            'email'    => [
                'required', 
                'email:rfc,dns', 
                Rule::unique('users')->ignore($userId)
            ],
        ]);

        $repartidor = $user ?: ($driver && $driver->user ? $driver->user : null);
        if ($repartidor) {
            $repartidor->name = strip_tags($request->nombre);
            $repartidor->phone = $request->telefono;
            $repartidor->email = $request->email;
            $repartidor->is_active = (bool) $request->estado;
            $repartidor->save();
        }

        if ($driver) {
            $driver->name = strip_tags($request->nombre);
            $driver->phone = $request->telefono;
            $driver->email = $request->email;
            $driver->active = (bool) $request->estado;
            $driver->status = $request->estado ? 'active' : 'inactive';
            $driver->save();
        }

        $retDriver = $driver ?: DeliveryDriver::where('user_id', $repartidor?->id)->first();

        return response()->json([
            'mensaje'    => 'Repartidor actualizado correctamente',
            'message'    => 'Repartidor actualizado correctamente',
            'id'         => $retDriver ? $retDriver->id : ($repartidor ? $repartidor->id : $id),
            'name'       => $repartidor ? $repartidor->name : ($retDriver ? $retDriver->name : strip_tags($request->nombre)),
            'nombre'     => $repartidor ? $repartidor->name : ($retDriver ? $retDriver->name : strip_tags($request->nombre)),
            'phone'      => $repartidor ? $repartidor->phone : ($retDriver ? $retDriver->phone : $request->telefono),
            'telefono'   => $repartidor ? $repartidor->phone : ($retDriver ? $retDriver->phone : $request->telefono),
            'email'      => $request->email,
            'active'     => (bool) $request->estado,
            'is_active'  => (bool) $request->estado,
            'estado'     => (bool) $request->estado,
            'repartidor' => [
                'id'        => $repartidor ? $repartidor->id : ($retDriver ? $retDriver->id : $id),
                'nombre'    => $repartidor ? $repartidor->name : ($retDriver ? $retDriver->name : strip_tags($request->nombre)),
                'name'      => $repartidor ? $repartidor->name : ($retDriver ? $retDriver->name : strip_tags($request->nombre)),
                'email'     => $request->email,
                'telefono'  => $repartidor ? $repartidor->phone : ($retDriver ? $retDriver->phone : $request->telefono),
                'phone'     => $repartidor ? $repartidor->phone : ($retDriver ? $retDriver->phone : $request->telefono),
                'estado'    => (bool) $request->estado,
                'is_active' => (bool) $request->estado,
                'active'    => (bool) $request->estado,
            ]
        ]);
    }

    public function destroy($id)
    {
        $driver = DeliveryDriver::findOrFail($id);

        // Check if driver has assigned deliveries
        if ($driver->deliveries()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un repartidor con pedidos asignados'
            ], 422);
        }

        if ($driver->user) {
            $driver->user->delete();
        }

        $driver->delete();

        return response()->json(['message' => 'Repartidor eliminado']);
    }

    public function toggle($id)
    {
        $driver = DeliveryDriver::findOrFail($id);
        $newActive = !$driver->active;

        $driver->update([
            'active' => $newActive,
            'status' => $newActive ? 'active' : 'inactive',
        ]);

        return response()->json([
            'id'     => $driver->id,
            'name'   => $driver->name,
            'phone'  => $driver->phone,
            'email'  => $driver->email,
            'active' => (bool) $driver->active,
        ]);
    }
}
