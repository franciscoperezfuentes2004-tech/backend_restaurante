<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Throwable;

class ImageCompressionService
{
    /**
     * Comprime y redimensiona una imagen subida, convirtiéndola al formato WebP.
     *
     * @param UploadedFile|string $file Archivo subido o ruta del archivo
     * @param string $folder Carpeta destino dentro de storage/app/public (ej: 'dishes', 'categories', 'logos')
     * @param int $maxWidth Ancho máximo permitido en píxeles (default: 800px)
     * @param int $quality Calidad de compresión WebP (default: 75%)
     * @param string $prefix Prefijo para el nombre único del archivo
     * @return array{path: string, url: string, filename: string}
     */
    public static function compressAndStore(
        UploadedFile|string $file,
        string $folder = 'dishes',
        int $maxWidth = 800,
        int $quality = 75,
        string $prefix = 'img_'
    ): array {
        $folder = trim($folder, '/');
        $uniqueId = \Illuminate\Support\Str::uuid()->toString();

        try {
            // Instanciar ImageManager con driver GD
            $manager = new ImageManager(new GdDriver());

            // Decodificar la imagen recibida
            $image = $manager->decode($file);

            // Redimensionar manteniendo relación de aspecto sin sobre-escalar fotos pequeñas
            if ($image->width() > $maxWidth) {
                $image->scaleDown(width: $maxWidth);
            }

            // Codificar a formato WebP con la calidad deseada
            $encoded = $image->encodeUsingFileExtension('webp', quality: $quality);

            $filename = "{$uniqueId}.webp";
            $relativePath = "{$folder}/{$filename}";

            // Almacenar en el disco public
            Storage::disk('public')->put($relativePath, (string) $encoded);

            return [
                'path'     => $relativePath,
                'url'      => Storage::url($relativePath),
                'filename' => $filename,
            ];
        } catch (Throwable $e) {
            Log::warning("ImageCompressionService: No se pudo comprimir la imagen con Intervention, usando fallback nativo. Error: " . $e->getMessage());

            // Fallback seguro: guardar el archivo directamente si es UploadedFile
            if ($file instanceof UploadedFile) {
                $path = $file->store($folder, 'public');
                return [
                    'path'     => $path,
                    'url'      => Storage::url($path),
                    'filename' => basename($path),
                ];
            }

            throw $e;
        }
    }

    /**
     * Elimina una imagen previa del almacenamiento si pertenece al disco público.
     *
     * @param string|null $imageUrl URL completa o relativa de la imagen previa
     * @param string $folder Carpeta donde debería residir (ej: 'dishes', 'categories')
     */
    public static function deleteOldImage(?string $imageUrl, string $folder = ''): bool
    {
        if (empty($imageUrl)) {
            return false;
        }

        try {
            $folder = trim($folder, '/');
            $needle = $folder ? "/storage/{$folder}/" : '/storage/';

            if (str_contains($imageUrl, $needle)) {
                $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
                $relativePath = $folder ? "{$folder}/{$filename}" : $filename;

                if (Storage::disk('public')->exists($relativePath)) {
                    return Storage::disk('public')->delete($relativePath);
                }
            }
        } catch (Throwable $e) {
            Log::warning("ImageCompressionService: Error al eliminar imagen previa ({$imageUrl}): " . $e->getMessage());
        }

        return false;
    }
}
