<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Migration;

use Exception;

/**
 * Exception class thrown when migrations cannot be reversed using the 'change'
 * feature.
 */
class IrreversibleMigrationException extends Exception
{
}
