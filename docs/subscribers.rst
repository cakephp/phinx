.. index::
single: Event Subscribers

Commands ``migrate`` and ``rollback`` fire of events ``phinx.command.migrate`` and ``phinx.command.rollback`` accordingly. It allows developers to do some regular things after migrations and rollbacks through event subscribers.

Every event subscriber must implement an interface ``\Symfony\Component\EventDispatcher\EventSubscriberInterface``. If it doesn't, then it will be skipped quietly.

.. code-block:: php

    <?php

    use Symfony\Component\EventDispatcher\EventSubscriberInterface;

    class ClearCache implements EventSubscriberInterface
    {
        public static function getSubscribedEvents()
        {
            return [
                \Phinx\Console\CommandEvents::MIGRATE  => "onClearCache",
                \Phinx\Console\CommandEvents::ROLLBACK => "onClearCache",
            ];
        }

        public function onClearCache()
        {
            \\ do some things
        }
    }

All event subscribers must be placed in the directory specified in the configuration file.
