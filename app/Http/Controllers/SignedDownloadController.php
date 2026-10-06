<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * Software / tool downloads for the app. The app has no web session, so the
 * API hands out short-lived signed links (5 minutes) after checking the
 * order belongs to the caller; the same delivery rules as the web apply.
 */
class SignedDownloadController extends Controller
{
    public function software(Order $order)
    {
        abort_unless($order->isSoftware() && $order->softwareAccessActive(), 403, 'Software download is locked.');

        $product = $order->product;
        $disk = Storage::disk(config('software.download_disk', 'private'));
        abort_unless($product->software_file && $disk->exists($product->software_file), 404);

        return $disk->download($product->software_file, $product->software_filename ?: basename($product->software_file));
    }

    public function tool(Order $order)
    {
        abort_unless($order->isToolOrder() && $order->isConfirmed(), 403, 'This download opens once payment is approved.');

        $download = $order->tool->primaryDownload;
        $disk = Storage::disk(config('software.download_disk', 'private'));
        abort_unless($download && $disk->exists($download->file_path), 404);

        return $disk->download($download->file_path, $download->file_filename);
    }
}
