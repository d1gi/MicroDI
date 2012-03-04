<?php

namespace MicroDI\Test;

use MicroDI\Test\Adapter\AdapterInterface;
use PDO;

class TestService
{
    /**
     * @var AltTestService
     */
    protected $altTestService;

    /**
     *
     * @var ArrayObject <AdapterInterface>
     */
    protected $adapters;

    /**
     * @var AdapterInterface
     */
    protected $someAdapter;

    protected $simpleValue = 222;

    /**
     * @var PDO
     */
    private $db;

    public function __construct(AdapterInterface $someAdapter, $simpleValue = 0)
    {
        $this->someAdapter = $someAdapter;
        $this->simpleValue = $simpleValue;
    }

    public function getSomething()
    {
        return $this->altTestService->getValue();
    }
}
