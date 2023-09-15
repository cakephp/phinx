<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Adapter;

use RuntimeException;

/**
 * Exception thrown when a column type doesn't match a Phinx type.
 */
class UnsupportedColumnTypeException extends RuntimeException
{
}
