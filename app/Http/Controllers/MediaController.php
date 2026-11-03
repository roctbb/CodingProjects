<?php

namespace App\Http\Controllers;

use App\RoomCharacterGeneration;
use App\Services\RoomCharacterImages;
use Illuminate\Http\Request;

use App\Http\Requests;
use Response;
use Storage;

class MediaController extends Controller
{
    public function roomCharacterPreview(int $generation_id, RoomCharacterImages $images)
    {
        $generation = RoomCharacterGeneration::visibleForShowcase()->findOrFail($generation_id);
        abort_unless(Storage::exists($generation->result_path), 404);
        $path = $generation->previewPath();
        if (!Storage::exists($path)) {
            // Build existing characters' small previews on demand, outside the homepage request.
            $bytes = $images->preview(Storage::get($generation->result_path));
            abort_unless(Storage::put($path, $bytes), 503);
        }

        return Response::make(Storage::get($path), 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=10800');
    }

    /**
     * @param $dir
     * @param $name
     * @return \Illuminate\Http\Response
     */
    public function index($dir, $name)
    {
        $name = $dir.'/'.$name;
        if (!Storage::exists($name)) {
            return Response::make('File no found.', 404);
        }
        $file = Storage::get($name);
        $type = Storage::mimeType($name);
        $response = Response::make($file, 200)
            ->header("Content-Type", $type)
            ->header("Cache-Control", "public, max-age=" . (3 * 60 * 60)); // кэширование на три часа
        return $response;
    }
}
