<?php

namespace LiteCQRS;

abstract class AggregateRoot implements AggregateRootInterface
{
    private $appliedEvents;

    public function getAppliedEvents()
    {
        return $this->appliedEvents;
    }

    public function popAppliedEvents()
    {
        $events = $this->appliedEvents;
        $this->appliedEvents = array();
        return $events;
    }

    protected function apply(DomainEvent $event)
    {
        $this->executeEvent($event);
        $this->appliedEvents[] = $event;
    }

    private function executeEvent(DomainEvent $event)
    {
        $method = sprintf('apply%s', $event->getEventName());

        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException("There is no event named '$method' that can be applied to '" . get_class($this) . "'");
        }

        $this->$method($event);
    }

    public function loadFromHistory(array $events)
    {
        foreach ($events as $event) {
            $this->executeEvent($event);
        }
    }
}


