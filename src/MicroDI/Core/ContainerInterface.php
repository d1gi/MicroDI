<?php
/**
 * Micro dependency injection container interface
 *
 * @author      Sergey Yuferev <s.yuferev@findgoods.ru>
 * @category    MicroDI
 * @package     Core
 */

namespace MicroDI\Core;

/**
 * @author      Sergey Yuferev <s.yuferev@findgoods.ru>
 * @category    MicroDI
 * @package     Core
 */
interface ContainerInterface
{
    /**
     * Set config
     *
     * @param  ArrayObject|array $config
     * @return Container
     */
    public function setConfig($config);

    /**
     * Load and build service by name
     *
     * @param  string $serviceName
     * @throws ServiceNotFoundException
     * @throws ServiceClassNotFoundException
     * @return Object service
     */
    public function get($serviceName);

    /**
     * Touch all services described in config, so preloads them
     *
     * @return Container
     */
    public function preLoad();
}
