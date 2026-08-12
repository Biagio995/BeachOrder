<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->load(['items', 'location']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->order->tenant_id.'.orders'),
            // Public: customers track a single order without staff auth.
            new Channel('tenant.'.$this->order->tenant_id.'.orders.'.$this->order->id),
            new PrivateChannel('tenant.'.$this->order->tenant_id.'.location.'.$this->order->location_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => $this->order->toArray(),
        ];
    }
}
