<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Validators\OfficeValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    public function index()
    {

        $offices = Office::query()
            ->where('approval_status',Office::APPROVAL_APPROVED)
            ->where('hidden',false)
            ->when(request('user_id'), fn ($builder) => $builder->whereUserId(request('user_id')))
            ->when(request('visitor_id'), fn (
                Builder $builder) => $builder->whereRelation('reservations','user_id','=',request('visitor_id')  ))
            ->when(
                request('lat') && request('lng'),
                    fn(Builder $builder) => $builder->nearestTo(request('lat'),request('lng')),
                    fn(Builder $builder) => $builder->orderBy('id','ASC')
            )
            ->with(['tags','images','user'])
            ->withCount(['reservations' => fn (Builder $builder) =>$builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);
        return OfficeResource::collection(
            $offices
        );

    }
    public function show(Office $office)
    {
        $office->loadcount(['reservations' => fn (Builder $builder) =>$builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['tags','images','user']);

        return OfficeResource::make($office);
    }

    public function create()
    {

        if(!auth()->user()->tokenCan('office.create'))
            abort(Response::HTTP_FORBIDDEN);
        $office = new Office();
        $attributes = (new OfficeValidator())->validate($office,request()->all());

        $attributes['user_id'] = auth()->id();
        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = DB::transaction(function() use ($office,$attributes) {
            $office->fill(
                Arr::except($attributes,['tags'])
            )->save();
            if(isset($attributes['tags']))
            {
                $office->tags()->sync($attributes['tags']);
            }

            return $office;
        });

        return OfficeResource::make(
            $office->load(['tags','images','user'])
        );
    }
    public function update(Office $office)
    {
        if(!auth()->user()->tokenCan('office.update'))
            abort(Response::HTTP_FORBIDDEN);
        $this->authorize('update',$office);
        $attributes = (new OfficeValidator())->validate($office,request()->all());
        $attributes['user_id'] = auth()->id();
        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        DB::transaction(function() use ($attributes,$office) {
             $office->update(
                Arr::except($attributes,['tags'])
            );
             if(isset($attributes['tags']))
             {
                 $office->tags()->sync($attributes['tags']);
             }


        });
        return OfficeResource::make(
            $office->load(['tags','images','user'])
        );
    }
}
