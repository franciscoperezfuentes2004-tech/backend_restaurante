<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DeliveryDriver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepartidorController extends Controller
{
    public function update(Request $request, $id)
    {
        // Encontrar repartidor ya sea por ID de User o DeliveryDriver
        $user = User::find($id);
        $driver = null;

        if ($user) {
            $userId = $user->id;
            $driver = DeliveryDriver::where('user_id', $user->id)->first();
        } else {
            $driver = DeliveryDriver::find($id);
            $userId = $driver ? $driver->user_id : $id;
            $user = $driver && $driver->user ? $driver->user : User::find($userId);
        }

        // Normalizar nombres de atributos si vienen en inglés
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

        $repartidor = $user ?: User::findOrFail($id);
        
        // Purificar antes de guardar (Anti-XSS)
        $repartidor->name = strip_tags($request->nombre);
        $repartidor->phone = $request->telefono;
        $repartidor->email = $request->email;
        $repartidor->is_active = (bool) $request->estado;
        $repartidor->save();

        if ($driver) {
            $driver->name = strip_tags($request->nombre);
            $driver->phone = $request->telefono;
            $driver->email = $request->email;
            $driver->active = (bool) $request->estado;
            $driver->status = $request->estado ? 'active' : 'inactive';
            $driver->save();
        }

        return response()->json([
            'mensaje' => 'Repartidor actualizado correctamente',
            'message' => 'Repartidor actualizado correctamente',
            'repartidor' => [
                'id'        => $repartidor->id,
                'nombre'    => $repartidor->name,
                'name'      => $repartidor->name,
                'email'     => $repartidor->email,
                'telefono'  => $repartidor->phone,
                'phone'     => $repartidor->phone,
                'estado'    => (bool) $repartidor->is_active,
                'is_active' => (bool) $repartidor->is_active,
            ]
        ]);
    }
}