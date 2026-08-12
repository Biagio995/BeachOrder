<?php

namespace App\Services\Pos;

use App\Contracts\Pos\POSAdapterInterface;
use App\Models\PosIntegration;
use App\Services\Pos\Adapters\CustomPosAdapter;
use App\Services\Pos\Adapters\EpsilonPylonAdapter;
use App\Services\Pos\Adapters\SoftOneAdapter;
use InvalidArgumentException;

class PosAdapterRegistry
{
    /** @var array<string, POSAdapterInterface> */
    private array $adapters = [];

    public function __construct()
    {
        $this->register(new EpsilonPylonAdapter);
        $this->register(new SoftOneAdapter);
        $this->register(new CustomPosAdapter);
    }

    public function register(POSAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->provider()] = $adapter;
    }

    public function for(PosIntegration $integration): POSAdapterInterface
    {
        $adapter = $this->adapters[$integration->provider] ?? null;
        if ($adapter === null) {
            throw new InvalidArgumentException("Unsupported POS provider [{$integration->provider}].");
        }

        return $adapter;
    }

    /**
     * @return list<string>
     */
    public function providers(): array
    {
        return array_keys($this->adapters);
    }
}
