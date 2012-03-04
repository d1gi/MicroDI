<?php
/**
 * Micro dependency injection container
 *
 * @author      Sergey Yuferev <s.yuferev@findgoods.ru>
 * @category    MicroDI
 * @package     Core
 */

namespace MicroDI\Core;

use ArrayObject;
use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;
use ReflectionParameter;

use InvalidArgumentException as ServiceNotFoundException;
use InvalidArgumentException as ServiceClassNotFoundException;
use InvalidArgumentException as ServiceHasNoPropertyException;
use InvalidArgumentException as ServiceHasNoMethodException;
use InvalidArgumentException as ServiceMethodHasNoParameterException;
use InvalidArgumentException as ServiceRecursiveCallException;
/**
 * @author      Sergey Yuferev <s.yuferev@findgoods.ru>
 * @category    MicroDI
 * @package     Core
 */
class Container implements ContainerInterface
{
    /**
     * @var ArrayObject
     */
    protected $config;

    /**
     * @var ArrayObject
     */
    protected $services;

    /**
     * @var ArrayObject
     */
    protected $buildLocks;

    public function __construct($config = null)
    {
        if ($config) {
            $this->setConfig($config);
        } else {
            $this->config = new ArrayObject;
        }

        $this->services   = new ArrayObject;
        $this->buildLocks = new ArrayObject;
    }

    /**
     * Set config
     *
     * @param  ArrayObject|array $config
     * @return Container
     */
    public function setConfig($config)
    {
        $this->config = $config instanceOf ArrayObject
            ? $config
            : new ArrayObject($config);

        return $this;
    }

    /**
     * Convert string value
     * If it starts with @ - find and load as service
     *
     * @param  string $configValue
     * @return string
     */
    private function convertPropertyStringValue($configValue)
    {
        $isService = substr($configValue, 0, 1) == '@';

        if ($isService) {
            $serviceName = substr($configValue, 1);
            $value       = $this->get($serviceName);
        } else {
            $value = $configValue;
        }

        return $value;
    }

    /**
     * Convert config value to object property value
     *
     * @param  mixed $configValue
     * @return mixed
     */
    private function convertPropertyValue($configValue)
    {
        if (is_string($configValue)) {
            $configValue = $this->convertPropertyStringValue($configValue);
        } else if (is_array($configValue)) {
            foreach($configValue as & $item) {
                if (is_string($item)) {
                    $item = $this->convertPropertyStringValue($item);
                }
            }
        }

        return $configValue;
    }

    /**
     * Convert all params to args: load composite services
     *
     * @param  array $params
     * @param  ReflectionMethod|null $method
     * @param  ReflectionClass|null $meta
     * @return array converted params
     */
    private function convertArgs(array $params,
        ReflectionMethod $method = null,
        ReflectionClass $meta = null
    ) {
        $args = array();

        $parametersNames = array();

        if($method) {
            $parameters = $method->getParameters();
            /* @var $parameter ReflectionParameter */
            foreach($parameters as $parameter) {
                $parametersNames[$parameter->getName()] = $parameter;
            }
        }

        foreach($params as $propertyName => $configValue) {

            if($method) {
                if (!isset($parametersNames[$propertyName])) {
                    throw new ServiceMethodHasNoParameterException(sprintf(
                        'Method %s has no parameter with name %s, only %s available',
                        $method->getName(),
                        $propertyName,
                        implode(', ', array_keys($parametersNames))
                    ));
                }
            }

            if($meta) {
                if (!$meta->hasProperty($propertyName)) {
                    throw new ServiceHasNoPropertyException(sprintf(
                        'Service %s has no property %s', $meta->getName(),
                        $propertyName
                    ));
                }
            }

            $args[$propertyName] = $this->convertPropertyValue($configValue);
        }

        return $args;
    }

    /**
     * Load properties to service object from config
     *
     * @param  ReflectionClass $meta
     * @param  Object $service
     * @param  array $params
     * @throws ServiceHasNoPropertyException
     * @return Object service
     */
    private function loadProperties(ReflectionClass $meta, $service, array $params)
    {
        $args = $this->convertArgs($params, null, $meta);

        foreach($args as $propertyName => $value) {
            $property = $meta->getProperty($propertyName);
            $private  = $property->isPrivate() || $property->isProtected();
            $property->setAccessible(true);
            $property->setValue($service, $value);
            if ($private) {
                $property->setAccessible(false);
            }
        }

        return $service;
    }

    /**
     * Load and set params to service constructor
     *
     * @param  ReflectionClass $meta
     * @param  array $params
     * @return Object
     */
    private function loadConstructor(ReflectionClass $meta, array $params)
    {
        $method = $meta->getConstructor();
        if (!$method) {
            throw new ServiceHasNoMethodException(sprintf(
                'Service %s has no method %s', $meta->getName(),
                '__construct'
            ));
        }

        $args = $this->convertArgs($params, $method, null);

        return $meta->newInstanceArgs($args);
    }

    /**
     * Load method data from config
     *
     * @param  ReflectionClass $meta
     * @param  string $methodName
     * @param  array $params
     * @param  Object $service
     * @throws ServiceHasNoMethodException
     */
    private function loadMethod(ReflectionClass $meta, $methodName,
        array $params, $service)
    {
        /* @var $method ReflectionMethod */
        $method = $meta->getMethod($methodName);
        if (!$method) {
            throw new ServiceHasNoMethodException(sprintf(
                'Service %s has no method %s', $meta->getName(),
                $methodName
            ));
        }

        $args   = $this->convertArgs($params, $method, null);

        return $method->invokeArgs($service, $args);
    }

    /**
     * Load all methods with params
     *
     * @param ReflectionClass $meta
     * @param Object $service
     * @param array $params methods array
     */
    private function loadMethods(ReflectionClass $meta, $service, array $params)
    {
        foreach($params as $methodName => $methodParams) {
            $this->loadMethod($meta, $methodName, $methodParams, $service);
        }

        return $this;
    }

    /**
     * Load service object and setup from config params
     *
     * @param  string $className
     * @param  array $params
     * @return Object
     */
    private function buildService($className, array $params)
    {
        $meta = new ReflectionClass($className);
        if(isset($params['construct'])) {
            $service = $this->loadConstructor($meta, $params['construct']);
        } else {
            $service = new $className;
        }

        if(isset($params['properties'])) {
            $this->loadProperties($meta, $service, $params['properties']);
        }

        if(isset($params['methods'])) {
            $this->loadMethods($meta, $service, $params['methods']);
        }

        return $service;
    }

    /**
     * Load and build service by name
     *
     * @param  string $serviceName
     * @throws ServiceNotFoundException
     * @throws ServiceClassNotFoundException
     * @return Object service
     */
    public function get($serviceName)
    {
        if ($this->services->offsetExists($serviceName)) {
            return $this->services->offsetGet($serviceName);
        }

        if($this->buildLocks->offsetExists($serviceName)) {
            throw new ServiceRecursiveCallException(sprintf(
                'Recursive dependencies with %s service',
                $serviceName
            ));
        }

        if (!$this->config->offsetExists($serviceName)) {
            throw new ServiceNotFoundException(sprintf(
                'Service not found in config: %s', $serviceName
            ));
        }

        $params = $this->config->offsetGet($serviceName);

        if(!isset($params['class'])) {
            throw new ServiceClassNotFoundException(sprintf(
                'Service class not found in service %s', $serviceName
            ));
        }

        $this->buildLocks->offsetSet($serviceName, true);
        $service = $this->buildService($params['class'], $params);
        $this->buildLocks->offsetUnset($serviceName);

        $this->services->offsetSet($serviceName, $service);

        return $service;
    }

    /**
     * Touch all services described in config, so preloads them
     *
     * @return Container
     */
    public function preLoad()
    {
        foreach($this->config as $serviceName => $params) {
            $this->get($serviceName);
        }

        return $this;
    }
}
