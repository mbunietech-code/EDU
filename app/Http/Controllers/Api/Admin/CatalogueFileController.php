<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The downloadable installer of a software product or a research tool,
 * uploaded from the app. Same rules and private disk as the web forms
 * (Admin\ProductController / Admin\ToolController).
 */
class CatalogueFileController extends Controller
{
    private const RULES = ['required', 'file', 'extensions:exe,zip,msi,rar,apk,dmg', 'max:153600'];

    public function storeProductFile(Request $request, Product $product): JsonResponse
    {
        $this->allow($request, 'products.manage');
        $request->validate(['file' => self::RULES], $this->messages());

        if ($product->type !== 'software') {
            throw ValidationException::withMessages(['file' => 'Only software products have a download. Change the product type to Software first.']);
        }

        $old = $product->software_file;
        $product->forceFill([
            'software_file' => $request->file('file')->store('software', $this->disk()),
            'software_filename' => $request->file('file')->getClientOriginalName(),
        ])->save();
        $this->deleteStored($old);

        ActivityLog::log('product_software_uploaded', 'Product', $product->id, ['file' => $product->software_filename]);

        return response()->json(['data' => $this->productFile($product), 'message' => 'Installer uploaded.']);
    }

    public function destroyProductFile(Request $request, Product $product): JsonResponse
    {
        $this->allow($request, 'products.manage');
        $this->deleteStored($product->software_file);
        $product->forceFill(['software_file' => null, 'software_filename' => null])->save();

        return response()->json(['data' => $this->productFile($product), 'message' => 'Installer removed.']);
    }

    public function storeToolFile(Request $request, Tool $tool): JsonResponse
    {
        $this->allow($request, 'tools.manage');
        $request->validate(['file' => self::RULES], $this->messages());

        $file = $request->file('file');
        $name = $file->getClientOriginalName();
        $path = $file->store('tools', $this->disk());

        $this->deleteToolDownload($tool);
        $tool->downloads()->create([
            'file_path' => $path,
            'file_filename' => $name,
            'file_type' => strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'bin',
            'file_size' => $file->getSize(),
            'sort_order' => 0,
        ]);

        ActivityLog::log('tool_file_uploaded', 'Tool', $tool->id, ['file' => $name]);

        return response()->json(['data' => $this->toolFile($tool->refresh()), 'message' => 'Tool file uploaded.']);
    }

    public function destroyToolFile(Request $request, Tool $tool): JsonResponse
    {
        $this->allow($request, 'tools.manage');
        $this->deleteToolDownload($tool);

        return response()->json(['data' => $this->toolFile($tool->refresh()), 'message' => 'Tool file removed.']);
    }

    /** @return array{name:?string,has_file:bool} */
    public static function productFile(Product $product): array
    {
        return ['name' => $product->software_filename, 'has_file' => (bool) $product->software_file];
    }

    /** @return array{name:?string,size:?int,has_file:bool} */
    public static function toolFile(Tool $tool): array
    {
        $download = $tool->primaryDownload;

        return ['name' => $download?->file_filename, 'size' => $download?->file_size, 'has_file' => $download !== null];
    }

    private function deleteToolDownload(Tool $tool): void
    {
        $download = $tool->primaryDownload;
        if ($download) {
            $this->deleteStored($download->file_path);
            $download->delete();
        }
    }

    private function deleteStored(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    private function disk(): string
    {
        return config('software.download_disk', 'private');
    }

    private function messages(): array
    {
        return [
            'file.extensions' => 'Upload an installer: exe, zip, msi, rar, apk or dmg.',
            'file.max' => 'The file may not be larger than 150 MB.',
            'file.uploaded' => 'The upload failed. The file may be larger than the server allows.',
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->hasPermission($permission), 403, 'You do not have permission to change the catalogue.');
    }
}
