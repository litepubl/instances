<?php

namespace LitePubl\Container\Container;

use LitePubl\Container\Interfaces\ContainerInterface;
use LitePubl\Container\Interfaces\EventsInterface;
use LitePubl\Container\Interfaces\FactoryInterface;
use LitePubl\Container\Exceptions\NotFound;
use LitePubl\Container\Exceptions\CircleException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use \IteratorAggregate;
use \ArrayIterator;

class Container implements ContainerInterface, IteratorAggregate
{
    protected $items;
    protected $factory;
    protected $events;
    protected $circleNames;

    public function __construct(FactoryInterface $factory, EventsInterface $events)
    {
        $this->factory = $factory;
        $this->events = $events;
        $this->circleNames = [];
        $this->items = [
        'factory' => $factory,
        get_class($factory) => $factory,
         get_class($this) => $this,
            ContainerInterface::class => $this,
            PsrContainerInterface::class => $this,
            'container' => $this,
            'instances' => $this,
            'services' => $this,
        'instanceEvents' => $events,
        ];
    }

    public function getFactory(): FactoryInterface
    {
        return $this->factory;
    }

    public function setFactory(FactoryInterface $factory): void
    {
        $this->factory = $factory;
    }

    public function getEvents(): EventsInterface
    {
        return $this->events;
    }

    public function setEvents(EventsInterface $events): void
    {
        $this->events = $events;
    }

    public function get($className)
    {
        $className = ltrim($className, '\\');
        $result = $this->events->onBeforeGet($className);
        if (!$result && isset($this->items[$className])) {
            $result = $this->items[$className];
        }

        if (!$result) {
            if (!class_exists($className)) {
                throw new NotFound($className);
            }
        
            if (in_array($className, $this->circleNames)) {
                throw new CircleException(sprintf('Class "%s" has circle dependencies, current classes stack ', $className, implode("\n", $this->circleNames)));
            }
        
            $this->circleNames[] = $className;
            try {
                $result = $this->createInstance($className);
                $this->items[$className] = $result;
            } finally {
                array_pop($this->circleNames);
            }
        }
 
        $this->events->onAfterGet($className, $result);
        return $result;
    }

    public function has($className)
    {
        return array_key_exists(ltrim($className, '\\'), $this->items);
    }

    public function set(object $instance, ? string $name): void
    {
        $this->items[get_class($instance)] = $instance;
        if ($name) {
            $name = ltrim($name, '\\');
            $this->items[$name] = $instance;
        }

        $this->events->onSet($instance, $name);
    }

    public function createInstance(string $className): object
    {
        $result = $this->events->onBeforeCreate($className);
        if (!$result) {
            if ($newClass = $this->factory->getImplementation($className)) {
                $result = $this->get($newClass);
            } elseif ($this->factory->has($className)) {
                $result = $this->factory->get($className);
            } else {
                $result = $this->events->onNotFound($className);
                if (!$result) {
                                throw new NotFound($className);
                }
            }
        }

        $this->events->onAfterCreate($className, $result);
                return $result;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    public function delete(string $className): bool
    {
        if (isset($this->items[$className])) {
                unset($this->items[$className]);
            $this->events->onDeleted($className);
                return true;
        }

        return false;
    }

    public function remove(object $instance): bool
    {
        $result = false;
        foreach ($this->items as $name => $item) {
            if ($instance === $item) {
                unset($this->items[$name]);
                $result = true;
            }
        }

        if ($result) {
            $this->events->onRemoved($instance);
        }

        return $result;
    }
}
