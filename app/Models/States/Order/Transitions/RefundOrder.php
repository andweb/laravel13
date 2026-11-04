<?php

namespace App\Models\States\Order\Transitions;

use App\Models\Order;
use App\Models\States\Order\Refunded;
use Spatie\ModelStates\Transition;

class RefundOrder extends Transition
{
    public function __construct(private Order $order) {}

    public function canTransition(): bool
    {
        return (bool) config('orders.refunds_enabled');
    }

    public function handle(): Order
    {
        $this->order->status = new Refunded($this->order);
        $this->order->save();

        return $this->order;
    }
}
