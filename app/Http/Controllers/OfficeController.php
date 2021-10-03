<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class OfficeController extends Controller
{
    public function index()
    {

        $offices = Office::query()
            ->when(request('user_id') && request('user_id') == auth()->id(),
                fn ($builder) => $builder,
                fn ($builder) => $builder->where('hidden',false)->where('approval_status',Office::APPROVAL_APPROVED),

            )
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
        Notification::send(User::where('is_admin',true)->get(), new OfficePendingApproval($office));


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
        $office->fill(Arr::except($attributes,['tags']));

        if($requiresReview = $office->isDirty(['lat','lng','price_per_day']))
            $office->fill(['approval_status' =>Office::APPROVAL_PENDING]);

        DB::transaction(function() use ($attributes,$office) {
             $office->save();
             if(isset($attributes['tags']))
             {
                 $office->tags()->sync($attributes['tags']);
             }

        });
        if ($requiresReview)
        {
            Notification::send(User::where('is_admin',true)->get(), new OfficePendingApproval($office));
        }
        return OfficeResource::make(
            $office->load(['tags','images','user'])
        );
    }

    public function delete(Office $office)
    {
        if(!auth()->user()->tokenCan('office.delete'))
            abort(Response::HTTP_FORBIDDEN);

        $this->authorize('delete', $office);

        throw_if(
            $office->reservations()->where('status',Reservation::STATUS_ACTIVE)->exists(),
            ValidationException::withMessages(['office'=>'cannot delete this office'])

        );

        $office->delete();
    }
}
