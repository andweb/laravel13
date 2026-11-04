<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = request()->user()
            ->orders()
            ->with(['user', 'lines'])
            ->latest()
            ->get();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $order = $request->user()->orders()->create([
                'total' => $request->validated('total'),
            ]);

            $order->lines()->createMany($request->validated('lines'));

            return $order->load(['user', 'lines']);
        });

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['user', 'lines']));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $order = DB::transaction(function () use ($request, $order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $locked->status->transitionTo($request->validated('status'));

            return $locked->load(['user', 'lines']);
        });

        return new OrderResource($order);
    }
}
