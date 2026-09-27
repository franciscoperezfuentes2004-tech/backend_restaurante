<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum', 'web', 'api']]);

// Canal de pedidos / órdenes (accesible por cualquier usuario autenticado, incluidos meseros)
Broadcast::channel('orders', function ($user) {
    return $user !== null;
}, ['guards' => ['sanctum', 'web', 'api']]);

Broadcast::channel('kitchen.orders', function ($user) {
    return in_array($user->role, ['cocina', 'kitchen', 'admin', 'super_admin', 'gerente', 'mesero', 'waiter', 'cajero']);
}, ['guards' => ['sanctum', 'web', 'api']]);

Broadcast::channel('waiter.orders', function ($user) {
    return in_array($user->role, ['mesero', 'waiter', 'cajero', 'admin', 'super_admin', 'gerente', 'cocina', 'kitchen']);
}, ['guards' => ['sanctum', 'web', 'api']]);

Broadcast::channel('mesas', function ($user) {
    return $user !== null;
}, ['guards' => ['sanctum', 'web', 'api']]);

Broadcast::channel('tables', function ($user) {
    return $user !== null;
}, ['guards' => ['sanctum', 'web', 'api']]);

Broadcast::channel('admin.notifications', function ($user) {
    return in_array($user->role, ['admin', 'super_admin', 'gerente']);
}, ['guards' => ['sanctum', 'web', 'api']]);

Broadcast::channel('delivery.cuts', function ($user) {
    return in_array($user->role, ['admin', 'super_admin', 'gerente', 'repartidor']);
}, ['guards' => ['sanctum', 'web', 'api']]);
