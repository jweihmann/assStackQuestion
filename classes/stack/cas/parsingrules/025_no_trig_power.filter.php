<?php
// This file is part of Stack - https://stack.maths.ed.ac.uk
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

require_once(__DIR__ . '/filter.interface.php');

/**
 * AST filter that identifies a specific use case related to trig functions
 * and powers. The 'sin^2(x)' case.
 */
class stack_ast_filter_025_no_trig_power implements stack_cas_astfilter {

    public function filter(MP_Node $ast, array &$errors, array &$answernotes, stack_cas_security $identifierrules): MP_Node {

        $selectednames = stack_cas_security::get_all_with_feature('trigfun');

        $process = function($node) use (&$errors, &$answernotes, $selectednames) {
            // @codingStandardsIgnoreStart

            // Note we are not just looking for 'sin^' we want the context.
            //
            // The first case where the power is a float or integer
            // and the insertion of stars happens e.g. 'sin^2(x)':
            //
            // sin^2*(x)
            // --------- MP_Root
            // --------- MP_Statement
            // --------- MP_Operation * [insertstars]
            // -----     MP_Operation ^
            // ---       MP_Identifier sin
            //     -     MP_Integer 2
            //       --- MP_Group
            //        -  MP_Identifier x

            // The case sin^-2(x) gives:
            //sin^-2*(x)
            //------------MP_Root
            //---------- MP_Statement
            //---------- MP_Operation * [insertstars]!
            //------     MP_Operation ^!
            //---        MP_Identifier sin!
            //    --     MP_PrefixOp -!
            //     -     MP_Integer 2!
            //       --- MP_Group !
            //        -  MP_Identifier x!

            // @codingStandardsIgnoreEnd

            if ($node instanceof MP_Operation &&
                $node->op === '*' &&
                isset($node->position['insertstars']) &&
                $node->rhs instanceof MP_Group &&
                $node->lhs instanceof MP_Operation &&
                $node->lhs->op === '^' &&
                $node->lhs->lhs instanceof MP_Identifier &&
                array_key_exists($node->lhs->lhs->value, $selectednames)) {
                // Those rules should not match anything else.
                $node->position['invalid'] = true;
                // TODO: now that we have the whole "function call" as the $node
                // the error message could print out it all, but without that star...
                $errors[] = stack_string('stackCas_trigexp',
                    array('forbid' => stack_maxima_format_casstring($node->lhs->lhs->value.'^'),
                        'identifier' => $node->lhs->lhs->value));
                if (array_search('trigexp', $answernotes) === false) {
                    $answernotes[] = 'trigexp';
                }
                return true;
            }
            // @codingStandardsIgnoreStart

            // The other case has an identifier as the power and that leads to
            // parsing as a valid function call:
            //
            // sin^y(x)
            // -------- MP_Root
            // -------- MP_Statement
            // -------- MP_Operation ^
            // ---      MP_Identifier sin
            //     ---- MP_FunctionCall
            //     -    MP_Identifier y
            //       -  MP_Identifier x
            //
            // @codingStandardsIgnoreEnd
            if ($node instanceof MP_Operation &&
                $node->op === '^' &&
                $node->lhs instanceof MP_Identifier &&
                $node->rhs instanceof MP_FunctionCall &&
                array_key_exists($node->lhs->value, $selectednames)) {
                // Those rules should not match anything else.
                $node->position['invalid'] = true;
                $errors[] = stack_string('stackCas_trigexp',
                    array('forbid' => stack_maxima_format_casstring($node->lhs->value.'^'),
                        'identifier' => $node->lhs->value));
                if (array_search('trigexp', $answernotes) === false) {
                    $answernotes[] = 'trigexp';
                }
            }
            return true;
        };
        $ast->callbackRecurse($process);
        return $ast;
    }
}
