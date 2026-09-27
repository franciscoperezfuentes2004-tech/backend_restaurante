<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image'  => 'required_without:file|nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:25600',
            'file'   => 'required_without:image|nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:25600',
            'folder' => 'nullable|string',
        ], [
            'image.required_without' => 'El archivo de imagen es obligatorio.',
            'image.mimes'            => 'La imagen debe ser de tipo JPG, PNG, WebP, GIF o SVG.',
            'image.max'              => 'La imagen no debe superar los 25MB.',
            'file.mimes'             => 'La imagen debe ser de tipo JPG, PNG, WebP, GIF o SVG.',
            'file.max'               => 'La imagen no debe superar los 25MB.',
        ]);

        $file = $request->file('image') ?? $request->file('file');
        $folder = trim($request->input('folder', 'dishes'));

        if (empty($folder)) {
            $folder = 'dishes';
        }

        // Si es una imagen compatible (JPG, PNG, WebP), la comprimimos a WebP
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $compressed = ImageCompressionService::compressAndStore($file, $folder, 800, 75);
            $relativePath = $compressed['path'];
            $publicUrl = asset($compressed['url']);
        } else {
            // Store file on 'public' disk -> storage/app/public/{folder}
            $relativePath = $file->store($folder, 'public');
            // Generate public URL using Storage::url()
            $publicUrl = asset(Storage::url($relativePath));
        }

        AuditLogger::log('IMAGE_UPLOADED', 'media', "Imagen subida exitosamente en carpeta '{$folder}': {$relativePath}", $request->user(), 'info');

        return response()->json([
            'url'  => $publicUrl,
            'path' => $relativePath,
        ], 201);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;
        if (str_contains($path, '/storage/')) {
            $path = Str::after($path, '/storage/');
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            AuditLogger::log('IMAGE_DELETED', 'media', "Imagen eliminada: {$path}", $request->user(), 'info');
        }

        return response()->json(['message' => 'Imagen eliminada correctamente']);
    }
}
