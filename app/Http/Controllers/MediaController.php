<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Response;
use Storage;

class MediaController extends Controller
{
    /**
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
        $response = Response::make($file, 200)->header("Content-Type", $type);

        return $response;
    }
}