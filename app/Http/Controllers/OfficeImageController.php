<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Office;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;


class OfficeImageController extends Controller
{
    public function store(Office $office)
    {

        if(!auth()->user()->tokenCan('office.update'))
            abort(Response::HTTP_FORBIDDEN);

        $this->authorize('update',$office);

         request()->validate([
             'image' => ['file','max:5000','mimes:jpg,png']
         ]);
         $path = request()->file('image')->storePublicly('/');

         $image = $office->images()->create([
             'path' => $path
         ]);
         return ImageResource::make($image);
    }

    public function delete(Office $office, Image $image)
    {
        if(!auth()->user()->tokenCan('office.update'))
            abort(Response::HTTP_FORBIDDEN);

        $this->authorize('update',$office);

        throw_if(

            $office->images()->count() == 1,
            ValidationException::withMessages(['images' => 'Cannot delete the only image'])
        );

        throw_if(
            $office->featured_image_id == $image->id,
            ValidationException::withMessages(['images' => 'Cannot delete the featured image'])
        );
        Storage::delete($image->path);
        $image->delete();
    }
}
