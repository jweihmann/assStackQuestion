<?php
/**
 * Copyright (c) Laboratorio de Soluciones del Sur, Sociedad Limitada
 * GPLv3, see LICENSE
 */

/**
 * STACK Question IMPORT OF QUESTIONS from an ILIAS file
 *
 * @author Jesús Copado Mejías <stack@surlabs.es>
 * @version $Id: 7.1$
 * @ingroup    ModulesTestQuestionPool
 *
 */

require_once './Services/MediaObjects/classes/class.ilObjMediaObject.php';
require_once './Modules/TestQuestionPool/classes/import/qti12/class.assQuestionImport.php';


class assStackQuestionImport extends assQuestionImport
{
    /** @var assStackQuestion */
    var $object;

    /**
     * @param assStackQuestion $object
     */
    public function __construct(assStackQuestion $object)
    {
        $this->object = $object;
    }

    /**
     * Receives parameters from a QTI parser and creates a valid ILIAS question object
     *
     * @param object $item The QTI item object
     * @param integer $questionpool_id The id of the parent questionpool
     * @param integer $tst_id The id of the parent test if the question is part of a test
     * @param object $tst_object A reference to the parent test object
     * @param integer $question_counter A reference to a question counter to count the questions of an imported question pool
     * @param array $import_mapping An array containing references to included ILIAS objects
     * @access public
     */
    public function fromXML(&$item, $questionpool_id, &$tst_id, &$tst_object, &$question_counter, &$import_mapping)
    {

        global $DIC;
        $ilUser = $DIC['ilUser'];
        // empty session variable for imported xhtml mobs
        unset($_SESSION["import_mob_xhtml"]);

        $presentation = $item->getPresentation();
        $duration = $item->getDuration();
        $shuffle = 0;
        $selectionLimit = null;
        $now = getdate();
        $created = sprintf("%04d%02d%02d%02d%02d%02d", $now['year'], $now['mon'], $now['mday'], $now['hours'], $now['minutes'], $now['seconds']);
        $answers = array();

        //Obtain question general data
        $this->addGeneralMetadata($item);
        $this->object->setTitle($item->getTitle());
        $this->object->setNrOfTries($item->getMaxattempts());
        $this->object->setComment($item->getComment());
        $this->object->setAuthor($item->getAuthor());
        $this->object->setOwner($ilUser->getId());
        $this->object->setQuestion($this->object->QTIMaterialToString($item->getQuestiontext()));
        $this->object->setObjId($questionpool_id);
        $this->object->setPoints((float)$item->getMetadataEntry("POINTS"));
        $this->object->setEstimatedWorkingTime($duration["h"], $duration["m"], $duration["s"]);

        $this->object->saveQuestionDataToDb();

        //question
        $stack_question = unserialize(base64_decode($item->getMetadataEntry('stack_question')));

        //New style
        if (is_array($stack_question)) {

            $this->object = assStackQuestionUtils::_arrayToQuestion($stack_question, $this->object);

        } else {

            //Old Style

            //Objects
            $this->object->getPlugin()->includeClass("model/ilias_object/class.assStackQuestionOptions.php");
            /* @var assStackQuestionOptions $options_obj */
            $options_obj = unserialize(base64_decode($item->getMetadataEntry('options')));
            $this->object->question_variables = $options_obj->getQuestionVariables();

            $this->object->specific_feedback = $this->processNonAbstractedImageReferences($options_obj->getSpecificFeedback(), $item->getIliasSourceNic());
            $this->object->prt_correct = $this->processNonAbstractedImageReferences($options_obj->getPRTCorrect(), $item->getIliasSourceNic());
            $this->object->prt_incorrect = $this->processNonAbstractedImageReferences($options_obj->getPRTIncorrect(), $item->getIliasSourceNic());
            $this->object->prt_partially_correct = $this->processNonAbstractedImageReferences($options_obj->getPRTPartiallyCorrect(), $item->getIliasSourceNic());
            $this->object->question_note = $this->processNonAbstractedImageReferences($options_obj->getQuestionNote(), $item->getIliasSourceNic());
            $this->object->variants_selection_seed = '';
            $this->object->stack_version = '';

            //options
            $options = array();
            $options['simplify'] = ((int)$options_obj->getQuestionSimplify());
            $options['assumepos'] = ((int)$options_obj->getAssumePositive());
            $options['assumereal'] = ((int)1);
            $options['multiplicationsign'] = ilUtil::secureString((string)$options_obj->getMultiplicationSign());
            $options['sqrtsign'] = ((int)$options_obj->getSqrtSign());
            $options['complexno'] = ilUtil::secureString((string)$options_obj->getComplexNumbers());
            $options['inversetrig'] = ilUtil::secureString((string)$options_obj->getInverseTrig());
            $options['matrixparens'] = ilUtil::secureString((string)$options_obj->getMatrixParens());
            $options['logicsymbol'] = ilUtil::secureString('lang');

            //load options
            try {
                $this->object->options = new stack_options($options);
                //set stack version
                if (isset($question->stackversion->text)) {
                    $this->object->stack_version = (string)ilUtil::secureString((string)$question->stackversion->text);
                }
            } catch (stack_exception $e) {
                $this->error_log[] = $this->object->getTitle() . ': options not created';
            }

            //STEP 3: load xqcas_inputs fields
            //old format load
            $this->object->getPlugin()->includeClass("model/ilias_object/class.assStackQuestionInput.php");
            $inputs_raw = unserialize(base64_decode($item->getMetadataEntry('inputs')));
            $required_parameters = stack_input_factory::get_parameters_used();

            //load all inputs present in the old XML
            /* @var assStackQuestionInput $input */
            foreach ($inputs_raw as $input_name => $input) {


                $input_name = ilUtil::secureString((string)$input_name);
                $input_type = ilUtil::secureString((string)$input->getInputType());

                $all_parameters = array(
                    'boxWidth' => ilUtil::secureString((string)$input->getBoxSize()),
                    'strictSyntax' => ilUtil::secureString((string)$input->getStrictSyntax()),
                    'insertStars' => ilUtil::secureString((string)$input->getInsertStars()),
                    'syntaxHint' => ilUtil::secureString((string)$input->getSyntaxHint()),
                    'syntaxAttribute' => '',
                    'forbidWords' => ilUtil::secureString((string)$input->getForbidWords()),
                    'allowWords' => ilUtil::secureString((string)$input->getAllowWords()),
                    'forbidFloats' => ilUtil::secureString((string)$input->getForbidFloat()),
                    'lowestTerms' => ilUtil::secureString((string)$input->getRequireLowestTerms()),
                    'sameType' => ilUtil::secureString((string)$input->getCheckAnswerType()),
                    'mustVerify' => ilUtil::secureString((string)$input->getMustVerify()),
                    'showValidation' => ilUtil::secureString((string)$input->getShowValidation()),
                    'options' => ilUtil::secureString((string)$input->getOptions()),
                );

                $parameters = array();
                foreach ($required_parameters[$input_type] as $parameter_name) {
                    if ($parameter_name == 'inputType') {
                        continue;
                    }
                    $parameters[$parameter_name] = $all_parameters[$parameter_name];
                }

                //load inputs
                try {
                    $this->object->inputs[$input_name] = stack_input_factory::make($input_type, $input_name, ilUtil::secureString((string)$input->getTeacherAnswer()), $this->object->options, $parameters);
                } catch (stack_exception $e) {
                    $this->object->error_log[] = $this->object->getTitle() . ': ' . $e;
                }
            }

            //PRTs
            /* @var assStackQuestionPRT $prt */
            /* @var assStackQuestionPRTNode $node */
            $this->object->getPlugin()->includeClass("model/ilias_object/class.assStackQuestionPRT.php");
            $this->object->getPlugin()->includeClass("model/ilias_object/class.assStackQuestionPRTNode.php");
            $prts = unserialize(base64_decode($item->getMetadataEntry('prts')));
            foreach ($prts as $prt_name => $prt) {
                foreach ($prt->getPRTNodes() as $node_name => $node) {
                    $node->setFalseFeedback($this->processNonAbstractedImageReferences($node->getFalseFeedback(), $item->getIliasSourceNic()));
                    $node->setTrueFeedback($this->processNonAbstractedImageReferences($node->getTrueFeedback(), $item->getIliasSourceNic()));
                }
            }

            //STEP 4:load PRTs and PRT nodes

            //Values
            $total_value = 0;
            foreach ($prts as $prt_data) {
                $total_value += (float)ilUtil::secureString((string)$prt_data->getPRTValue());
            }

            if ($total_value < 0.0000001) {
                $total_value = 1.0;
            }

            /* @var assStackQuestionPRT $prt */
            foreach ($prts as $prt) {
                $first_node = 1;

                $prt_name = ilUtil::secureString((string)$prt->getPRTName());
                $nodes = array();
                $is_first_node = true;
                $invalid_node = false;

                //Check for non "0" nodes
                /*
                foreach ($prt->node as $xml_node) {
                    if ($xml_node->name == '0') {
                        $invalid_node = true;
                    }
                }*/

                /* @var assStackQuestionPRTNode $xml_node */
                foreach ($prt->getPRTNodes() as $xml_node) {

                    $node_name = ilUtil::secureString((string)$xml_node->getNodeName());

                    $raw_sans = assStackQuestionUtils::_debugText((string)$xml_node->getStudentAnswer());
                    $raw_tans = assStackQuestionUtils::_debugText((string)$xml_node->getTeacherAnswer());

                    $sans = stack_ast_container::make_from_teacher_source('PRSANS' . $node_name . ':' . $raw_sans, '', new stack_cas_security());
                    $tans = stack_ast_container::make_from_teacher_source('PRTANS' . $node_name . ':' . $raw_tans, '', new stack_cas_security());

                    //Penalties management, penalties are not an ILIAS Feature
                    $false_penalty = ilUtil::secureString((string)$xml_node->getFalsePenalty());
                    $true_penalty = ilUtil::secureString((string)$xml_node->getTruePenalty());

                    try {
                        //Create Node and add it to the
                        $node = new stack_potentialresponse_node($sans, $tans, ilUtil::secureString((string)$xml_node->getAnswerTest()), ilUtil::secureString((string)$xml_node->getTestOptions()), (bool)(string)$xml_node->getQuiet(), '', (int)$node_name, $raw_sans, $raw_tans);

                        //manage images in true feedback
                        if (isset($xml_node->falsefeedback->text)) {
                            $false_feedback = (string)$xml_node->falsefeedback->text;

                        } else {
                            $false_feedback = '';
                        }

                        //manage images in true feedback
                        if (isset($xml_node->truefeedback->text)) {
                            $true_feedback = (string)$xml_node->truefeedback->text;

                        } else {
                            $true_feedback = '';
                        }

                        $false_next_node = $xml_node->getFalseNextNode();
                        $true_next_node = $xml_node->getTrueNextNode();
                        $false_answer_note = $xml_node->getFalseAnswerNote();
                        $true_answer_note = $xml_node->getTrueAnswerNote();

                        $node->add_branch(0, ilUtil::secureString((string)$xml_node->getFalseScoreMode()), ilUtil::secureString((string)$xml_node->getFalseScore()), $false_penalty, ilUtil::secureString((string)$false_next_node), ilUtil::secureString($false_feedback), 1, ilUtil::secureString((string)$false_answer_note));
                        $node->add_branch(1, ilUtil::secureString((string)$xml_node->getTrueScoreMode()), ilUtil::secureString((string)$xml_node->getTrueScore()), $true_penalty, ilUtil::secureString((string)$true_next_node), ilUtil::secureString($true_feedback), 1, ilUtil::secureString((string)$true_answer_note));

                        $nodes[$node_name] = $node;

                        //set first node
                        if ($is_first_node) {
                            $first_node = $node_name;
                            $is_first_node = false;
                        }

                    } catch (stack_exception $e) {
                        $this->error_log[] = $this->object->getTitle() . ': ' . $e;
                    }
                }

                $feedback_variables = null;
                if ((string)$prt->getPRTFeedbackVariables()) {
                    try {
                        $feedback_variables = new stack_cas_keyval(assStackQuestionUtils::_debugText((string)$prt->getPRTFeedbackVariables()));
                        $feedback_variables = $feedback_variables->get_session();
                    } catch (stack_exception $e) {
                        $this->error_log[] = $this->object->getTitle() . ': ' . $e;
                    }
                }

                $prt_value = (float)$prt->getPRTValue() / $total_value;

                try {
                    $this->object->prts[$prt_name] = new stack_potentialresponse_tree($prt_name, '', (bool)$prt->getAutoSimplify(), $prt_value, $feedback_variables, $nodes, (string)$first_node, 1);
                } catch (stack_exception $e) {
                    $this->error_log[] = $this->object->getTitle() . ': ' . $e;
                }
            }

            //SEEDS
            $this->object->getPlugin()->includeClass("model/ilias_object/class.assStackQuestionDeployedSeed.php");
            $deployed_seeds = unserialize(base64_decode($item->getMetadataEntry('seeds')));

            //TODO Not done
            $seeds = array();
            /*
            if (isset($question->deployedseed)) {
                foreach ($question->deployedseed as $seed) {
                    $seeds[] = (int)ilUtil::secureString((string)$seed);
                }
            }*/
            $this->object->deployed_seeds = $seeds;

            //TESTS
            $this->object->getPlugin()->includeClass("model/ilias_object/test/class.assStackQuestionTest.php");
            $this->object->getPlugin()->includeClass("model/ilias_object/test/class.assStackQuestionTestInput.php");
            $this->object->getPlugin()->includeClass("model/ilias_object/test/class.assStackQuestionTestExpected.php");
            $unit_tests = unserialize(base64_decode($item->getMetadataEntry('tests')));

            //EXTRA INFO
            /* @var assStackQuestionExtraInfo $extra_info */
            $this->object->getPlugin()->includeClass("model/ilias_object/class.assStackQuestionExtraInfo.php");
            $extra_info = unserialize(base64_decode($item->getMetadataEntry('extra_info')));
            $extra_info->setHowToSolve($this->processNonAbstractedImageReferences($extra_info->getHowToSolve(), $item->getIliasSourceNic()));

            $this->object->general_feedback = $extra_info->getHowToSolve();
            // Don't save the question additionally to DB before media object handling
            // this would create double rows for options, prts etc.
        }

        // Don't save the question additionally to DB before media object handling
        // this would create double rows for options, prts etc.

        /*********************************
         * Media object handling
         * @see assClozeTestImport
         ********************************/

        // handle the import of media objects in XHTML code
        $question_text = $this->object->getQuestion();

        if (is_array($_SESSION["import_mob_xhtml"])) {

            include_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";
            include_once "./Services/RTE/classes/class.ilRTE.php";

            foreach ($_SESSION["import_mob_xhtml"] as $mob) {
                if ($tst_id > 0) {
                    //#22754
                    $importfile = $this->getTstImportArchivDirectory() . '/' . current(explode('?', $mob["uri"]));
                } else {
                    //#22754
                    $importfile = $this->getQplImportArchivDirectory() . '/' . current(explode('?', $mob["uri"]));
                }

                $GLOBALS['ilLog']->write(__METHOD__ . ': import mob from dir: ' . $importfile);

                $media_object = ilObjMediaObject::_saveTempFileAsMediaObject(basename($importfile), $importfile, false);
                ilObjMediaObject::_saveUsage($media_object->getId(), "qpl:html", $this->object->getId());

                $question_text = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $question_text);

                $this->object->specific_feedback = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $this->object->specific_feedback);

                $this->object->prt_correct = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $this->object->prt_correct);
                $this->object->prt_partially_correct = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $this->object->prt_partially_correct);
                $this->object->prt_incorrect = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $this->object->prt_incorrect);

                $this->object->general_feedback = str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $this->object->general_feedback);

                foreach ($this->object->prts as $prt) {
                    foreach ($prt->getNodes() as $node) {
                        $feedback = $node->getFeedbackFromNode();
                        $node->setBranchFeedback(0, str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $feedback['false_feedback']));
                        $node->setBranchFeedback(1, str_replace("src=\"" . $mob["mob"] . "\"", "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"", $feedback['true_feedback']));
                    }
                }
            }
        }

        $this->object->setQuestion(ilRTE::_replaceMediaObjectImageSrc($question_text, 1));

        $this->object->specific_feedback = ilRTE::_replaceMediaObjectImageSrc($this->object->specific_feedback, 1);

        $this->object->prt_correct = ilRTE::_replaceMediaObjectImageSrc($this->object->prt_correct, 1);
        $this->object->prt_partially_correct = ilRTE::_replaceMediaObjectImageSrc($this->object->prt_partially_correct, 1);
        $this->object->prt_incorrect = ilRTE::_replaceMediaObjectImageSrc($this->object->prt_incorrect, 1);

        $this->object->general_feedback = ilRTE::_replaceMediaObjectImageSrc($this->object->general_feedback, 1);

        foreach ($this->object->prts as $prt) {
            foreach ($prt->getNodes() as $node) {

                $feedback = $node->getFeedbackFromNode();

                $node->setBranchFeedback(0, ilRTE::_replaceMediaObjectImageSrc($feedback['false_feedback'], 1));
                $node->setBranchFeedback(1, ilRTE::_replaceMediaObjectImageSrc($feedback['true_feedback'], 1));
            }
        }

        // now save the question as a whole
        $this->object->saveToDb();

        if ($tst_id > 0) {
            $q_1_id = $this->object->getId();
            $question_id = $this->object->duplicate(true, null, null, null, $tst_id);
            $tst_object->questions[$question_counter++] = $question_id;
            $import_mapping[$item->getIdent()] = array("pool" => $q_1_id, "test" => $question_id);
        } else {
            $import_mapping[$item->getIdent()] = array("pool" => $this->object->getId(), "test" => 0);
        }

        return $import_mapping;
    }
}
