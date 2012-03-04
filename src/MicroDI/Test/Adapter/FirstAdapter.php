<?php

namespace MicroDI\Test\Adapter;

class FirstAdapter implements AdapterInterface
{
    public function adapt($param)
    {
        return $param;
    }
}
