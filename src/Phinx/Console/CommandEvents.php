<?php

namespace Phinx\Console;

class CommandEvents
{
    /**
     * The event occurs when a migration is completed
     */
    const MIGRATE = 'phinx.command.migrate';

    /**
     * The event occurs when rollback is completed
     */
    const ROLLBACK = 'phinx.command.rollback';
}
