<?php

namespace App\Events;

use App\Models\WaiterCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaiterCallCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WaiterCall $waiterCall)
    {
        $this->waiterCall->load('location');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->waiterCall->tenant_id.'.waiter-calls'),
            new PrivateChannel('tenant.'.$this->waiterCall->tenant_id.'.location.'.$this->waiterCall->location_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'waiter-call.created';
    }

    public function broadcastWith(): array
    {
        return [
            'waiter_call' => $this->waiterCall->toArray(),
        ];
    }
}
