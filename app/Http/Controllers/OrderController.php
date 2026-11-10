<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\RecordOrderPaid;
use App\Models\Order;
use App\Models\States\Order\Paid;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
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
        $cacheKey = $this->idempotencyCacheKey($request);

        if ($cacheKey === null) {
            return $this->orderCreatedResponse($this->createOrder($request));
        }

        $response = Cache::lock($cacheKey.':lock', 10)->block(5, function () use ($request, $cacheKey) {
            $existing = $this->findIdempotentOrder($cacheKey);

            if ($existing instanceof Order) {
                return (new OrderResource($existing))->response();
            }

            $order = $this->createOrder($request);
            Cache::put($cacheKey, $order->id, now()->addMinutes(10));

            return $this->orderCreatedResponse($order);
        });

        return $response;
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        $order->load(['user', 'lines']);
        $order->status = Cache::remember(
            $this->statusCacheKey($order),
            60,
            fn () => (string) $order->status,
        );

        return new OrderResource($order);
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $order = DB::transaction(function () use ($request, $order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $locked->status->transitionTo($request->validated('status'));
            $locked->load(['user', 'lines']);
            Cache::put($this->statusCacheKey($locked), (string) $locked->status, 60);

            return $locked;
        });

        if ((string) $order->status === Paid::$name) {
            RecordOrderPaid::dispatch($order);
        }

        return new OrderResource($order);
    }

    private function createOrder(StoreOrderRequest $request): Order
    {
        $order = DB::transaction(function () use ($request) {
            $order = $request->user()->orders()->create([
                'total' => $request->validated('total'),
            ]);

            $order->lines()->createMany($request->validated('lines'));

            return $order->load(['user', 'lines']);
        });

        Cache::put($this->statusCacheKey($order), (string) $order->status, 60);

        return $order;
    }

    private function orderCreatedResponse(Order $order): JsonResponse
    {
        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    private function idempotencyCacheKey(StoreOrderRequest $request): ?string
    {
        $key = $request->headers->get('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return 'orders:idempotency:'.$request->user()->id.':'.$key;
    }

    private function findIdempotentOrder(string $cacheKey): ?Order
    {
        $orderId = Cache::get($cacheKey);

        if (! is_numeric($orderId)) {
            return null;
        }

        return Order::query()->with(['user', 'lines'])->find($orderId);
    }

    private function statusCacheKey(Order $order): string
    {
        return 'orders:'.$order->id.':status';
    }
}
