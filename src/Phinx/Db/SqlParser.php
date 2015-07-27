<?php
/**
 * Phinx
 *
 * (The MIT license)
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
 * @subpackage Phinx\Db
 */
namespace Phinx\Db;

class SqlParser
{
    /* QuoteStates */
    const NORMAL = 0;
    const SINGLE_QUOTED = 1;
    const DOUBLE_QUOTED = 2;
    const COMMENT_START = 3;
    const LINE_COMMENT = 4;
    const MULTI_COMMENT = 5;
    const COMMENT_END = 6;
    const ESCAPED = 7;

    /**
     * Split the script by non-quoted, non-commented, non-escaped semi-colons
     * 
     *    -- this is a comment;
     *    /* don't split; here
     *     *\/
     *    insert into table (col) values ('some; quoted text');
     *    update table set col='foo' where sep=';';
     * 
     * should be split into an array that looks like
     * 
     *    [ "-- this is a comment;\n/* don't split; here\n*\/\ninsert into table (col) values ('some; quoted text')",
     *      "update table set col='foo' where sep=';'"
     *    ]
     *    
     * or, if stripComments = true:
     * 
     *    ["insert into table (col) values ('some; quoted text')",
     *     "update table set col='foo' where sep=';']
     *    
     * NOTE: 
     *    * Nested comments are not currently supported
     *    * The entire script must be read into memory (TODO - optimize for streams?)
     * 
     * @param string script
     * @param boolean stripComments
     * @return an array of statements
     */
    public static function parse($script, $stripComments=false)
    {
        if ($script == null || preg_match('/^\s*$/', $script)) {
            return array();
        }
        
        $out = array();
        $len = strlen($script);
        $commentChar = 0;

        $capture = true;
        
        $stack = array();
        array_push($stack, SqlParser::NORMAL);
        
        $stmt = "";
        for ($i=0;$i<$len;$i++) {
            $c = $script{$i};
                
            switch (end($stack)) {
            case SqlParser::NORMAL:
                /*
                 * handle the following state changes
                 * 
                 * ESCAPE
                 * SINGLE_QUOTE
                 * DOUBLE_QUOTE
                 * COMMENT_START 
                 */
                $capture = true;
                switch ($c) {
                case ';':
                    if (strlen($stmt) > 0) {
                        $out[] = trim($stmt);
                    }
                    $stmt = "";
                    continue 3;
                    
                case '\'':
                    array_push($stack, SqlParser::SINGLE_QUOTED);
                    break;

                case '"':
                    array_push($stack, SqlParser::DOUBLE_QUOTED);
                    break;
                
                case '-':
                case '/':
                    $capture=false;
                    array_push($stack, SqlParser::COMMENT_START);
                    $commentChar = $c;
                    break;

                case '\\':
                    array_push($stack, SqlParser::ESCAPED);
                    break;
                }
                break;

            case SqlParser::COMMENT_START:
                /*
                 * handle 
                 *   MULTI_COMMENT
                 *   LINE_COMMENT
                 */
                array_pop($stack);
                if ($c == '-' && $commentChar == '-') {
                    array_push($stack, SqlParser::LINE_COMMENT);
                } elseif ($c == '*' && $commentChar == '/') {
                    array_push($stack, SqlParser::MULTI_COMMENT);
                } else {
                    $capture=true;
                    if ($stripComments) {
                        $stmt .= $commentChar;
                    }
                }
                break;
        
            case SqlParser::LINE_COMMENT:
                /*
                 * handle 
                 *   ESCAPE
                 *   COMMENT_END
                 */
                $capture = false;
                if ($c == '\\') {
                    array_push($stack, SqlParser::ESCAPED);
                } elseif ($c == "\n") {
                    array_pop($stack);
                }
                break;
                
            case SqlParser::MULTI_COMMENT:
                /*
                 * handle
                 *   ESCAPE
                 *   COMMENT_END
                 * NOTE: nested comments not supported
                 */
                $capture = false;
                if ($c == '\\') {
                    array_push($stack, SqlParser::ESCAPED);
                } elseif ($c == '*') {
                    array_push($stack, SqlParser::COMMENT_END);
                }
                /*else if( $c == '/' ) {                 
                    array_push($stack, SqlParser::COMMENT_START);
                    $commentChar = $c;
                }*/
                break;
                
            case SqlParser::COMMENT_END:
                array_pop($stack);
                if ($c == '/') {
                    array_pop($stack);
                }
                
            case SqlParser::SINGLE_QUOTED:
                /*
                 * handle 
                 *    ESCAPE
                 *    QUOTE_END
                 */
                if ($c == '\\') {
                    array_push($stack, SqlParser::ESCAPED);
                } elseif ($c == '\'') {
                    array_pop($stack);
                }
                break;
                
            case SqlParser::DOUBLE_QUOTED:
                /*
                 * handle
                 *    ESCAPE
                 *    QUOTE_END
                 */
                if ($c == '\\') {
                    array_push($stack, SqlParser::ESCAPED);
                } elseif ($c == '"') {
                    array_pop($stack);
                }
                break;
                
            case SqlParser::ESCAPED:
                array_pop($stack);
                break;
            }
            
            // don't print comments if requested
            if ($capture || !$stripComments) {
                $stmt .= $c;
            }
        }
        
        if (strlen($stmt) > 0) {
            if (!preg_match("/^\s*$/", $stmt)) {
                $out[] = $stmt;
            }
        }
        return $out;
    }
}
