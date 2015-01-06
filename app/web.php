<?php
/* Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
 * Copyright (c) 2014 Woody Gilk
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
 */

// This script can be run as a router with the built in PHP web server:
//
//   php -S localhost:8000 app/web.php
//
// Or can be run from any other web server with:
//
//   require 'phinx/app/web.php';
//
// This script uses the following query string arguments:
//
// - (string) "e" environment name
// - (string) "t" target version
// - (boolean) "debug" enable debugging?

// Get the phinx console application and inject it into TextWrapper.
 class Phinx {
        public static function migrate($environment = 'default') {
            $app = require __DIR__ . '/phinx.php';
            $wrap = new Phinx\Wrapper\TextWrapper($app);

            $output = call_user_func([$wrap, 'getMigrate'], $environment);
            $error  = $wrap->getExitCode() > 0;
            if($error) {
                return $error;
            }
            return true;
        }

        public static function status($environment = 'default') {
            $app = require __DIR__ . '/phinx.php';
            $wrap = new Phinx\Wrapper\TextWrapper($app);

            $output = call_user_func([$wrap, 'getStatus'], $environment);
            $error  = $wrap->getExitCode() > 0;
            if($error) {
                return $error;
            }
            return $output;
        }
    }
