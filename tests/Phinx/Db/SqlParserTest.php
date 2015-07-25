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

namespace Test\Phinx\Db;

use Phinx\Db\SqlParser;

class SqlParserTest extends \PHPUnit_Framework_TestCase
{
    static $script = "-- this is a comment;
/* don't split; here
 * insert into table (col) values ('this is commented out');
 * */
insert into table (col) values ('some; quoted text');
update table set col='foo' where sep=';';
insert into table (code) values ('--some comments; /*in text*/');";

    public function testSplit()
    {
        $commands = SqlParser::parse(SqlParserTest::$script);
        $this->assertEquals(
            3,
            count($commands)
        );

        $this->assertTrue(strpos($commands[0], '-- this is a comment') == 0);
        $this->assertEquals(
            $commands[1],
            "update table set col='foo' where sep=';'"
        );
    }

    public function testStripComments()
    {
        $commands = SqlParser::parse(SqlParserTest::$script,true);
        $this->assertEquals(
            3,
            count($commands)
        );
        $this->assertEquals(
            $commands[0],
            "insert into table (col) values ('some; quoted text')"
        );
        $this->assertEquals(
            $commands[2],
            "insert into table (code) values ('--some comments; /*in text*/')"
        );
    }
}
