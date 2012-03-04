<?php

namespace MicroDI\Test\Adapter;

class SecondAdapter implements AdapterInterface
{
    protected $state = false;

    public function adapt($param)
    {
        return $param;
    }

    public function setState($state)
    {
        $this->state = $state;

        return $state;
    }
}
