<?php
/**
 * Gitium provides automatic git version control and deployment for
 * your plugins and themes integrated into wp-admin.
 *
 * Copyright (C) 2014-2025 PRESSINFRA SRL <ping@presslabs.com>
 *
 * Gitium is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Gitium is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Gitium. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package         Gitium
 */

/* A Read-Eval-Print-Loop - Poor man's interactive debugger.

usage: repl(get_defined_vars());
       repl(get_defined_vars(), $this);

You can pass in multiple expressions separated by semicolons;
The value of the last expression will be returned.
 */
function repl($vars, $_this=null) {
    // inject the variables in the current scope
    // fwrite( STDOUT, "repl\n" );
    if ($_this !== null) {
        $vars['_this'] = $_this;
    }
    foreach($vars as $__name => $__value) {
        fwrite( STDOUT, '$'.$__name.'=$vars["'.$__name.'"];' . "\n" );
        if ($__name == 'this'){
            continue;
        }
        eval('$'.$__name.'=$vars["'.$__name.'"];');
    }
    $__ln = 1;
    while ( true ) {
        try {
            $__line = readline("repl $__ln> ");
            if ($__line === false)  # user presed ^D
                break;
            $__exploded = explode(";", $__line);
            $__last = count($__exploded) - 1;
            if ( $__exploded[$__last] === "" ) {
                array_pop($__exploded);
                $__last--;
            }
            $__exploded[$__last] = "return " . $__exploded[$__last];
            $__cmd = "";
            foreach($__exploded as $__statement) {
                $__cmd = $__cmd . $__statement . ";" ;
            }
            fwrite( STDOUT, print_r( eval( $__cmd ), true ) . "\n" );
            readline_add_history( $__line );
        } catch (Exception $__err) {
            fwrite( STDOUT, 'Exception: ' . $__err->getMessage() . "\n");
            fwrite( STDOUT, "Traceback: \n". $__err->getTraceAsString() . "\n");
        }
        $__ln++;
    }
}
