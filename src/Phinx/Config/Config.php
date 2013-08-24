<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2013 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 * 
 * @package    Phinx
 * @subpackage Phinx\Config
 */
namespace Phinx\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Phinx configuration class.
 *
 * @package Phinx
 * @author Rob Morgan
 */
class Config implements \ArrayAccess
{
    /**
     * @var array
     */
    private $values;
    
    /**
     * @var string
     */
    protected $configFilePath;
    
    /**
     * Class Constructor
     *
     * @param array $configArray Config Array
     * @param string $configFilePath Optional File Path
     * @return void
     */
    public function __construct($configArray, $configFilePath = null)
    {
        $this->configFilePath = $configFilePath;
        $this->values = $this->replaceTokens($configArray);
    }
    
    /**
     * Create a new instance of the config class using a Yaml file path.
     *
     * @param string $configFilePath Path to the Yaml File
     * @return Config
     */
    public static function fromYaml($configFilePath)
    {
        $configArray = Yaml::parse($configFilePath);
        return new self($configArray, $configFilePath);
    }
    
    /**
     * Create a new instance of the config class using a PHP file path.
     *
     * @param string $configFilePath Path to the PHP File
     * @return Config
     */
    public static function fromPHP($configFilePath)
    {
        ob_start();
        $configArray = include($configFilePath);
        
        // Hide console output
        $content = ob_get_clean();

        if (!is_array($configArray)) {
            throw new \RuntimeException(sprintf(
                'PHP file \'%s\' must return an array',
                $configFilePath
            ));
        }

        return new self($configArray, $configFilePath);
    }

    /**
     * Returns the configuration for each environment.
     * 
     * This method returns <code>null</code> if no environments exist.
     * 
     * @return array|null
     */
    public function getEnvironments()
    {
        if (isset($this->values) && isset($this->values['environments'])) {
            $environments = array();
            foreach ($this->values['environments'] as $key => $value) {
                if (is_array($value))
                    $environments[$key] = $value;
            }

            return $environments;
        }
            
        return null;
    }
    
    /**
     * Returns the configuration for a given environment.
     * 
     * This method returns <code>null</code> if the specified environment
     * doesn't exist.
     *
     * @return array|null
     */
    public function getEnvironment($name)
    {
        $environments = $this->getEnvironments();

        if (isset($environments[$name])) {
            if (isset($this->values['environments']['default_migration_table'])) {
                $environments[$name]['default_migration_table'] =
                    $this->values['environments']['default_migration_table'];
            }

            return $environments[$name];
        }
        
        return null;
    }
    
    /**
     * Does the specified environment exist in the configuration file?
     *
     * @param string $name Environment Name
     * @return void
     */
    public function hasEnvironment($name)
    {
        return (!(null === $this->getEnvironment($name)));
    }
    
    /**
     * Gets the default environment name.
     *
     * @return string
     */
    public function getDefaultEnvironment()
    {
        // The $PHINX_ENVIRONMENT variable overrides all other default settings
        $env = getenv('PHINX_ENVIRONMENT');
        if (!empty($env)) {
            if ($this->hasEnvironment($env)) {
                return $env;
            }
            
            throw new \RuntimeException(sprintf(
                'The environment configuration (read from $PHINX_ENVIRONMENT) for \'%s\' is missing',
                $env
            ));            
        }    

        // if the user has configured a default database then use it,
        // providing it actually exists!
        if (isset($this->values['environments']['default_database'])) {
            if ($this->getEnvironment($this->values['environments']['default_database'])) {
                return $this->values['environments']['default_database'];    
            }
            
            throw new \RuntimeException(sprintf(
                'The environment configuration for \'%s\' is missing',
                $this->values['environments']['default_database']
            ));
        }
     
        // else default to the first available one
        if (is_array($this->getEnvironments()) && count($this->getEnvironments()) > 0) {
            $names = array_keys($this->getEnvironments());
            return $names[0];
        }
        
        throw new \RuntimeException('Could not find a default environment');
    }
    
    /**
     * Gets the config file path.
     *
     * @return string
     */
    public function getConfigFilePath()
    {
        return $this->configFilePath;
    }
    
    /**
     * Gets the path of the migration files.
     *
     * @return string
     */
    public function getMigrationPath()
    {
        if (isset($this->values['paths']['migrations'])) {
            return realpath($this->values['paths']['migrations']);
        }
        
        return null;
    }
    
    /**
     * Replace tokens in the specified array.
     *
     * @param array $arr Array to replace
     * @return array
     */
    public function replaceTokens($arr)
    {
        // Get environment variables
        // $_ENV is empty because variables_order does not include it normally
        $tokens = array();
        foreach ($_SERVER as $varname => $varvalue) {
            if (0 === strpos($varname, 'PHINX_')) {
                $tokens['%%' . $varname . '%%'] = $varvalue;
            }
        }
        
        // Phinx defined tokens (override env tokens)
        $tokens['%%PHINX_CONFIG_PATH%%'] = $this->getConfigFilePath();
        $tokens['%%PHINX_CONFIG_DIR%%'] = dirname($this->getConfigFilePath());
        
        // Recurse the array and replace tokens
        if (is_array($arr)) {
            return $this->recurseArrayForTokens($arr, $tokens);
        }
        
        return $arr;
    }
    
    /**
     * Recurse an array for the specified tokens and replace them.
     *
     * @param array $arr Array to recurse
     * @param array $tokens Array of tokens to search for
     * @return array
     */
    public function recurseArrayForTokens($arr, $tokens)
    {
        $out = array();
        foreach ($arr as $name => $value) {
            if (is_array($value)) {
                $out[$name] = $this->recurseArrayForTokens($value, $tokens);
                continue;
            }
            if (is_string($value)) {
                foreach ($tokens as $token => $tval) {
                    $value = str_replace($token, $tval, $value);
                }
                $out[$name] = $value;
                continue;
            }
            $out[$name] = $value;
        }
        return $out;
    }
    
    /**
     * Sets a parameter or an object.
     *
     * Objects must be defined as Closures.
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same a name as an existing parameter would break your container).
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to defined an object
     * @return void
     */
    public function offsetSet($id, $value)
    {
        $this->values[$id] = $value;
    }

    /**
     * Gets a parameter or an object.
     *
     * @param  string $id The unique identifier for the parameter or object
     * @throws InvalidArgumentException if the identifier is not defined
     * @return mixed  The value of the parameter or an object
     */
    function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        return $this->values[$id] instanceof \Closure ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param  string $id The unique identifier for the parameter or object
     * @return boolean
     */
    function offsetExists($id)
    {
        return isset($this->values[$id]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param  string $id The unique identifier for the parameter or object
     * @return void
     */
    function offsetUnset($id)
    {
        unset($this->values[$id]);
    }
}