<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
}
