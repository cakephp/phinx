<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

class Util
{
    /**
     * Turn migration names like 'CreateUserTable' into file names like
     * '12345678901234_create_user_table.php' or 'LimitResourceNamesTo30Chars' into
     * '12345678901234_limit_resource_names_to_30_chars.php'.
     *
     * @param string $className Class Name
     * @return string
     */
    public static function mapClassNameToFileName($className)
    {
        $arr = preg_split('/(?=[A-Z])/', $className);
        unset($arr[0]); // remove the first element ('')
        $fileName = date('YmdHis') . '_' . strtolower(implode($arr, '_')) . '.php';
        return $fileName;
    }

    /**
     * Check if a migration class name is valid.
     *
     * Migration class names must be in CamelCase format.
     * e.g: CreateUserTable or AddIndexToPostsTable.
     *
     * Single words are not allowed on their own.
     *
     * @param string $className Class Name
     * @return boolean
     */
    public static function isValidMigrationClassName($className)
    {
        return (bool) preg_match('/^([A-Z][a-z0-9]+)+$/', $className);
    }

    /**
     * Removes comments from input SQL files.
     *
     * Input files must have Unix line endings.
     *
     * @see sql_parse.php of Thu May 31, 2001 from
     * The phpBB Group licensed under GPL2
     * @param string $fileContents File contents to filter
     * @return string $filteredOutput Filtered file content
     */
    public static function removeComments($fileContents)
    {
        $lines = explode("\n", $fileContents);

        $linecount = count($lines);

        $in_comment = false;
        $filteredOutput = '';

        for ($i = 0; $i < $linecount; $i++) {
            if (preg_match("/^\/\*/", preg_quote($lines[$i]))) {
                $in_comment = true;
            }

            if (!$in_comment) {
                $filteredOutput .= $lines[$i] . "\n";
            }

            if (preg_match("/\*\/$/", preg_quote($lines[$i]))) {
                $in_comment = false;
            }
        }

        unset($lines);
        return $filteredOutput;
    }

    /**
     * Removes remarks from input SQL files.
     *
     * Input files must have Unix line endings.
     *
     * @see sql_parse.php of Thu May 31, 2001 from
     * The phpBB Group licensed under GPL2
     * @param string $fileContents File contents to filter
     * @return string $filteredOutput Filtered file content
     */
    public static function removeRemarks($fileContents)
    {
        $lines = explode("\n", $fileContents);
        unset($fileContents);

        $linecount = count($lines);
        $filteredOutput = '';

        for ($i = 0; $i < $linecount; $i++) {
            if (($i != ($linecount - 1)) || (strlen($lines[$i]) > 0)) {
                if (isset($lines[$i][0]) && $lines[$i][0] != "#") {
                    $filteredOutput .= $lines[$i] . "\n";
                } else {
                    $filteredOutput .= "\n";
                }
                // Trading a bit of speed for lower mem. use here.
                $lines[$i] = "";
            }
        }

        return $filteredOutput;
    }

    /**
     * Splits input SQL files into isolated SQL statements.
     *
     * Input files must have Unix line endings.
     *
     * @see sql_parse.php of Thu May 31, 2001 from
     * The phpBB Group licensed under GPL2
     * @param string $fileContents File contents to filter
     * @param string $delimiter defaults to semicolon
     * @return array output array of type string containing all SQL stmt
     */
    public static function splitSqlFile($fileContents, $delimiter = ';')
    {
        // Split up our string into "possible" SQL statements.
        $tokens = explode($delimiter, $fileContents);
        unset($fileContents);
        $output = array();

        // we don't actually care about the matches preg gives us.
        $matches = array();

        // this is faster than calling count($tokens) every time thru the loop.
        $token_count = count($tokens);
        for ($i = 0; $i < $token_count; $i++) {
            // Don't wanna add an empty string as the last thing in the array.
            if (($i != ($token_count - 1)) || (strlen($tokens[$i] > 0))) {
                // This is the total number of single quotes in the token.
                $total_quotes = preg_match_all("/'/", $tokens[$i], $matches);
                // Counts single quotes that are preceded by an odd number of backslashes,
                // which means they're escaped quotes.
                $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);

                $unescaped_quotes = $total_quotes - $escaped_quotes;

                // If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
                if (($unescaped_quotes % 2) == 0) {
                    // It's a complete sql statement.
                    $output[] = $tokens[$i];
                    // save memory.
                    $tokens[$i] = "";
                } else {
                    // incomplete sql statement. keep adding tokens until we have a complete one.
                    // $temp will hold what we have so far.
                    $temp = $tokens[$i] . $delimiter;
                    // save memory..
                    $tokens[$i] = "";

                    // Do we have a complete statement yet?
                    $complete_stmt = false;

                    for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++) {
                        // This is the total number of single quotes in the token.
                        $total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
                        // Counts single quotes that are preceded by an odd number of backslashes,
                        // which means they're escaped quotes.
                        $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);

                        $unescaped_quotes = $total_quotes - $escaped_quotes;

                        if (($unescaped_quotes % 2) == 1) {
                            // odd number of unescaped quotes. In combination with the previous incomplete
                            // statement(s), we now have a complete statement. (2 odds always make an even)
                            $output[] = $temp . $tokens[$j];

                            // save memory.
                            $tokens[$j] = "";
                            $temp = "";

                            // exit the loop.
                            $complete_stmt = true;
                            // make sure the outer loop continues at the right point.
                            $i = $j;
                        } else {
                            // even number of unescaped quotes. We still don't have a complete statement.
                            // (1 odd and 1 even always make an odd)
                            $temp .= $tokens[$j] . $delimiter;
                            // save memory.
                            $tokens[$j] = "";
                        }

                    } // for..
                } // else
            }
        }

        return $output;
    }

    /*
     * Reads the content from a file and returns it as string
     *
     * @param $fileName File name of the file to read
     * @return string $fileContents Content of the file
     */
    public static function readFromFile($fileName)
    {
        return @fread(@fopen($fileName, 'r'), @filesize($fileName));
    }
}
