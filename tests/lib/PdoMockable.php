<?php
/**
 * Mocking PDO directly will cause an error when disabling the constructor.  Extending PDO and overriding the constructing get around this.
 */

class PdoMockable extends \PDO {
    public function __construct() {}
}