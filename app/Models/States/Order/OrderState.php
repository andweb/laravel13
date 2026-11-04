<?php

namespace App\Models\States\Order;

use Spatie\ModelStates\Attributes\AllowTransition;
use Spatie\ModelStates\Attributes\DefaultState;
use Spatie\ModelStates\State;

#[DefaultState(Draft::class)]
#[AllowTransition(Draft::class, AwaitingPayment::class)]
#[AllowTransition(AwaitingPayment::class, Paid::class)]
#[AllowTransition(Paid::class, Shipped::class)]
#[AllowTransition([Draft::class, AwaitingPayment::class], Cancelled::class)]
#[AllowTransition(Paid::class, Refunded::class, Transitions\RefundOrder::class)]
abstract class OrderState extends State
{
}
