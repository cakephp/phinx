<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Table;

use Phinx\Db\Adapter\AdapterInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * This object is based loosely on: http://api.rubyonrails.org/classes/ActiveRecord/ConnectionAdapters/Table.html.
 */
class Column
{
    public const BIGINTEGER = AdapterInterface::PHINX_TYPE_BIG_INTEGER;
    public const SMALLINTEGER = AdapterInterface::PHINX_TYPE_SMALL_INTEGER;
    public const BINARY = AdapterInterface::PHINX_TYPE_BINARY;
    public const BOOLEAN = AdapterInterface::PHINX_TYPE_BOOLEAN;
    public const CHAR = AdapterInterface::PHINX_TYPE_CHAR;
    public const DATE = AdapterInterface::PHINX_TYPE_DATE;
    public const DATETIME = AdapterInterface::PHINX_TYPE_DATETIME;
    public const DECIMAL = AdapterInterface::PHINX_TYPE_DECIMAL;
    public const FLOAT = AdapterInterface::PHINX_TYPE_FLOAT;
    public const INTEGER = AdapterInterface::PHINX_TYPE_INTEGER;
    public const STRING = AdapterInterface::PHINX_TYPE_STRING;
    public const TEXT = AdapterInterface::PHINX_TYPE_TEXT;
    public const TIME = AdapterInterface::PHINX_TYPE_TIME;
    public const TIMESTAMP = AdapterInterface::PHINX_TYPE_TIMESTAMP;
    public const UUID = AdapterInterface::PHINX_TYPE_UUID;
    public const BINARYUUID = AdapterInterface::PHINX_TYPE_BINARYUUID;
    /** MySQL-only column type */
    public const ENUM = AdapterInterface::PHINX_TYPE_ENUM;
    /** MySQL-only column type */
    public const SET = AdapterInterface::PHINX_TYPE_STRING;
    /** MySQL-only column type */
    public const BLOB = AdapterInterface::PHINX_TYPE_BLOB;
    /** MySQL-only column type */
    public const YEAR = AdapterInterface::PHINX_TYPE_YEAR;
    /** MySQL/Postgres-only column type */
    public const JSON = AdapterInterface::PHINX_TYPE_JSON;
    /** Postgres-only column type */
    public const JSONB = AdapterInterface::PHINX_TYPE_JSONB;
    /** Postgres-only column type */
    public const CIDR = AdapterInterface::PHINX_TYPE_CIDR;
    /** Postgres-only column type */
    public const INET = AdapterInterface::PHINX_TYPE_INET;
    /** Postgres-only column type */
    public const MACADDR = AdapterInterface::PHINX_TYPE_MACADDR;
    /** Postgres-only column type */
    public const INTERVAL = AdapterInterface::PHINX_TYPE_INTERVAL;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|\Phinx\Util\Literal
     */
    protected $type;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var bool
     */
    protected $null = false;

    /**
     * @var mixed|null
     */
    protected $default;

    /**
     * @var bool
     */
    protected $identity = false;

    /**
     * @var int
     */
    protected $seed;

    /**
     * @var int
     */
    protected $increment;

    /**
     * @var int
     */
    protected $scale;

    /**
     * @var string
     */
    protected $after;

    /**
     * @var string
     */
    protected $update;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var bool
     */
    protected $signed = true;

    /**
     * @var bool
     */
    protected $timezone = false;

    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @var string
     */
    protected $collation;

    /**
     * @var string
     */
    protected $encoding;

    /**
     * @var int|null
     */
    protected $srid;

    /**
     * @var array
     */
    protected $values;

    /**
     * Sets the column name.
     *
     * @param string $name Name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the column name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the column type.
     *
     * @param string|\Phinx\Util\Literal $type Column type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the column type.
     *
     * @return string|\Phinx\Util\Literal
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the column limit.
     *
     * @param int $limit Limit
     *
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Gets the column limit.
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Sets whether the column allows nulls.
     *
     * @param bool $null Null
     *
     * @return $this
     */
    public function setNull($null)
    {
        $this->null = (bool)$null;

        return $this;
    }

    /**
     * Gets whether the column allows nulls.
     *
     * @return bool
     */
    public function getNull()
    {
        return $this->null;
    }

    /**
     * Does the column allow nulls?
     *
     * @return bool
     */
    public function isNull()
    {
        return $this->getNull();
    }

    /**
     * Sets the default column value.
     *
     * @param mixed $default Default
     *
     * @return $this
     */
    public function setDefault($default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Gets the default column value.
     *
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Sets whether or not the column is an identity column.
     *
     * @param bool $identity Identity
     *
     * @return $this
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;

        return $this;
    }

    /**
     * Gets whether or not the column is an identity column.
     *
     * @return bool
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * Is the column an identity column?
     *
     * @return bool
     */
    public function isIdentity()
    {
        return $this->getIdentity();
    }

    /**
     * Sets the name of the column to add this column after.
     *
     * @param string $after After
     *
     * @return $this
     */
    public function setAfter($after)
    {
        $this->after = $after;

        return $this;
    }

    /**
     * Returns the name of the column to add this column after.
     *
     * @return string
     */
    public function getAfter()
    {
        return $this->after;
    }

    /**
     * Sets the 'ON UPDATE' mysql column function.
     *
     * @param string $update On Update function
     *
     * @return $this
     */
    public function setUpdate($update)
    {
        $this->update = $update;

        return $this;
    }

    /**
     * Returns the value of the ON UPDATE column function.
     *
     * @return string
     */
    public function getUpdate()
    {
        return $this->update;
    }

    /**
     * Sets the number precision for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $precision Number precision
     *
     * @return $this
     */
    public function setPrecision($precision)
    {
        $this->setLimit($precision);

        return $this;
    }

    /**
     * Gets the number precision for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @return int
     */
    public function getPrecision()
    {
        return $this->limit;
    }

    /**
     * Gets the column identity seed.
     *
     * @return int
     */
    public function getSeed()
    {
        return $this->seed;
    }

    /**
     * Gets the column identity increment.
     *
     * @return int
     */
    public function getIncrement()
    {
        return $this->increment;
    }

    /**
     * Sets the column identity seed.
     *
     * @param int $seed Number seed
     *
     * @return $this
     */
    public function setSeed($seed)
    {
        $this->seed = $seed;

        return $this;
    }

    /**
     * Sets the column identity increment.
     *
     * @param int $increment Number increment
     *
     * @return $this
     */
    public function setIncrement($increment)
    {
        $this->increment = $increment;

        return $this;
    }

    /**
     * Sets the number scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $scale Number scale
     *
     * @return $this
     */
    public function setScale($scale)
    {
        $this->scale = $scale;

        return $this;
    }

    /**
     * Gets the number scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @return int
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * Sets the number precision and scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $precision Number precision
     * @param int $scale Number scale
     *
     * @return $this
     */
    public function setPrecisionAndScale($precision, $scale)
    {
        $this->setLimit($precision);
        $this->scale = $scale;

        return $this;
    }

    /**
     * Sets the column comment.
     *
     * @param string $comment Comment
     *
     * @return $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gets the column comment.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Sets whether field should be signed.
     *
     * @param bool $signed Signed
     *
     * @return $this
     */
    public function setSigned($signed)
    {
        $this->signed = (bool)$signed;

        return $this;
    }

    /**
     * Gets whether field should be signed.
     *
     * @return bool
     */
    public function getSigned()
    {
        return $this->signed;
    }

    /**
     * Should the column be signed?
     *
     * @return bool
     */
    public function isSigned()
    {
        return $this->getSigned();
    }

    /**
     * Sets whether the field should have a timezone identifier.
     * Used for date/time columns only!
     *
     * @param bool $timezone Timezone
     *
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->timezone = (bool)$timezone;

        return $this;
    }

    /**
     * Gets whether field has a timezone identifier.
     *
     * @return bool
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Should the column have a timezone?
     *
     * @return bool
     */
    public function isTimezone()
    {
        return $this->getTimezone();
    }

    /**
     * Sets field properties.
     *
     * @param array $properties Properties
     *
     * @return $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Gets field properties
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Sets field values.
     *
     * @param string[]|string $values Value(s)
     *
     * @return $this
     */
    public function setValues($values)
    {
        if (!is_array($values)) {
            $values = preg_split('/,\s*/', $values);
        }
        $this->values = $values;

        return $this;
    }

    /**
     * Gets field values
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Sets the column collation.
     *
     * @param string $collation Collation
     *
     * @throws \UnexpectedValueException If collation not allowed for type
     *
     * @return $this
     */
    public function setCollation($collation)
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($this->getType(), $allowedTypes, true)) {
            throw new UnexpectedValueException('Collation may be set only for types: ' . implode(', ', $allowedTypes));
        }

        $this->collation = $collation;

        return $this;
    }

    /**
     * Gets the column collation.
     *
     * @return string
     */
    public function getCollation()
    {
        return $this->collation;
    }

    /**
     * Sets the column character set.
     *
     * @param string $encoding Encoding
     *
     * @throws \UnexpectedValueException If character set not allowed for type
     *
     * @return $this
     */
    public function setEncoding($encoding)
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($this->getType(), $allowedTypes, true)) {
            throw new UnexpectedValueException('Character set may be set only for types: ' . implode(', ', $allowedTypes));
        }

        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Gets the column character set.
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Sets the column SRID.
     *
     * @param int $srid SRID
     * @return \Phinx\Db\Table\Column
     */
    public function setSrid($srid)
    {
        $this->srid = $srid;

        return $this;
    }

    /**
     * Gets the column SRID.
     *
     * @return int|null
     */
    public function getSrid()
    {
        return $this->srid;
    }

    /**
     * Gets all allowed options. Each option must have a corresponding `setFoo` method.
     *
     * @return array
     */
    protected function getValidOptions()
    {
        return [
            'limit',
            'default',
            'null',
            'identity',
            'scale',
            'after',
            'update',
            'comment',
            'signed',
            'timezone',
            'properties',
            'values',
            'collation',
            'encoding',
            'srid',
            'seed',
            'increment',
        ];
    }

    /**
     * Gets all aliased options. Each alias must reference a valid option.
     *
     * @return array
     */
    protected function getAliasedOptions()
    {
        return [
            'length' => 'limit',
            'precision' => 'limit',
        ];
    }

    /**
     * Utility method that maps an array of column options to this objects methods.
     *
     * @param array $options Options
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function setOptions($options)
    {
        $validOptions = $this->getValidOptions();
        $aliasOptions = $this->getAliasedOptions();

        foreach ($options as $option => $value) {
            if (isset($aliasOptions[$option])) {
                // proxy alias -> option
                $option = $aliasOptions[$option];
            }

            if (!in_array($option, $validOptions, true)) {
                throw new RuntimeException(sprintf('"%s" is not a valid column option.', $option));
            }

            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }

        return $this;
    }
}
