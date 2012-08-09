<?php

namespace LiteCQRS\Bus;

use LiteCQRS\Command;
use LiteCQRS\EventStore\EventStoreInterface;
use LiteCQRS\EventStore\IdentityMapInterface;

/**
 * Command Bus accepts commands and finds the appropriate
 * handler to execute it.
 *
 * Execution of commands is wrapped into a UnitOfWork
 * transaction of the event storage. Events are only
 * published, if the command execution was succesful.
 */
abstract class CommandBus
{
    private $eventStore;
    private $identityMap;

    public function __construct(EventStoreInterface $eventStore, IdentityMapInterface $identityMap = null)
    {
        $this->eventStore  = $eventStore;
        $this->identityMap = $identityMap;
    }

    /**
     * Given a Commmand Type (ClassName) return an instance of
     * the service that is handling this command.
     *
     * @param string $commandType A Command Class name
     * @return object
     */
    abstract protected function getService($commandType);

    public function handle(Command $command)
    {
        $type    = get_class($command);
        $service = $this->getService($type);
        $method  = $this->getHandlerMethodName($command);

        $service = $this->proxyHandler($service, $method);

        $this->eventStore->beginTransaction(); // clear exisiting events

        try {
            $service->$method($command);

            $this->passEventsToStore();
            $this->eventStore->commit();

        } catch(\Exception $e) {
            $this->eventStore->rollback();

            throw $e;
        }
    }

    protected function passEventsToStore()
    {
        if (!$this->identityMap) {
            return;
        }

        foreach ($this->identityMap->all() as $aggregateRoot) {
            foreach ($aggregateRoot->popAppliedEvents() as $event) {
                $this->eventStore->add($event);
            }
        }
    }

    public function getHandlerMethodName($command)
    {
        $parts = explode("\\", get_class($command));
        return str_replace("Command", "", lcfirst(end($parts)));
    }

    protected function proxyHandler($service, $method)
    {
        return $service;
    }
}

