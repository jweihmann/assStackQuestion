<?php
/**
 * Copyright (c) Laboratorio de Soluciones del Sur, Sociedad Limitada
 * GPLv3, see LICENSE
 */

require_once './Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/classes/utils/class.assStackQuestionUtils.php';

/**
 * STACK Question MoodleXML Export
 * This class provides an XML compatible with Moodle for STACK questions created in ILIAS.
 *
 * @author Jesús Copado Mejías <stack@surlabs.es>
 * @version $Id: 7.1$
 * @ingroup    ModulesTestQuestionPool
 *
 */
class assStackQuestionMoodleXMLExport
{

	private array $stack_questions;

	function __construct($stack_questions)
	{
		global $DIC;

		$lng = $DIC->language();
		if (is_array($stack_questions) and sizeof($stack_questions)) {
			$this->setStackQuestions($stack_questions);

		} else {
			ilUtil::sendFailure($lng->txt('qpl_qst_xqcas_moodlexml_no_questions_selected'), true);
		}
	}


	/**
	 * @param assStackQuestion[] $stack_questions
	 */
	public function setStackQuestions(array $stack_questions)
	{
		$this->stack_questions = $stack_questions;
	}

	/**
	 * @return assStackQuestion[]
	 */
	public function getStackQuestions(): array
	{
		return $this->stack_questions;
	}


	function toMoodleXML(): void
	{
		global $ilias;

		include_once("./Services/Xml/classes/class.ilXmlWriter.php");
		$a_xml_writer = new ilXmlWriter;


		// set xml header
		$a_xml_writer->xmlHeader();
		$a_xml_writer->xmlStartTag("quiz");

		foreach ($this->getStackQuestions() as $question_id => $question) {
			//$a_xml_writer->xmlComment(" question: " . $question_id . " ");

			$a_xml_writer->xmlStartTag("question", array("type" => "stack"));
			//QUESTION

			//Question Title
			$a_xml_writer->xmlStartTag("name");
			$a_xml_writer->xmlElement("text", NULL, $question->getTitle());
			$a_xml_writer->xmlEndTag("name");

			//Question Text
			$a_xml_writer->xmlStartTag("questiontext", array("format" => "html"));
			$media = $this->getRTEMedia($question->getQuestion(), $question);
			$this->addRTEText($a_xml_writer, $question->getQuestion());
			$this->addRTEMedia($a_xml_writer, $media);
			$a_xml_writer->xmlEndTag("questiontext");

			//General feedback
			$a_xml_writer->xmlStartTag("generalfeedback", array("format" => "html"));
			$media = $this->getRTEMedia($question->general_feedback);
			$this->addRTEText($a_xml_writer, $question->general_feedback);
			$this->addRTEMedia($a_xml_writer, $media);
			$a_xml_writer->xmlEndTag("generalfeedback");

			//Grade and penalty
			$a_xml_writer->xmlElement("defaultgrade", NULL, $question->getPoints());
			if ($question->getPenalty()) {
				$a_xml_writer->xmlElement("penalty", NULL, $question->getPenalty());
			} else {
				$a_xml_writer->xmlElement("penalty", NULL, "0");
			}

			if ($question->getHidden()) {
				$a_xml_writer->xmlElement("hidden", NULL, $question->getHidden());
			} else {
				$a_xml_writer->xmlElement("hidden", NULL, "0");
			}

			//Options
			$a_xml_writer->xmlStartTag("questionvariables");
			$a_xml_writer->xmlElement("text", NULL, $question->question_variables);
			$a_xml_writer->xmlEndTag("questionvariables");

			$a_xml_writer->xmlStartTag("specificfeedback", array("format" => "html"));
			$media = $this->getRTEMedia($question->specific_feedback);
			$this->addRTEText($a_xml_writer, $question->specific_feedback);
			$this->addRTEMedia($a_xml_writer, $media);
			$a_xml_writer->xmlEndTag("specificfeedback");

			$a_xml_writer->xmlStartTag("questionnote", array("format" => "html"));
			$a_xml_writer->xmlElement("text", NULL, $question->question_note);
			$a_xml_writer->xmlEndTag("questionnote");

			$a_xml_writer->xmlStartTag("prtcorrect", array("format" => "html"));
			$media = $this->getRTEMedia($question->prt_correct);
			$this->addRTEText($a_xml_writer, $question->prt_correct);
			$this->addRTEMedia($a_xml_writer, $media);
			$a_xml_writer->xmlEndTag("prtcorrect");

			$a_xml_writer->xmlStartTag("prtpartiallycorrect", array("format" => "html"));
			$media = $this->getRTEMedia($question->prt_partially_correct);
			$this->addRTEText($a_xml_writer, $question->prt_partially_correct);
			$this->addRTEMedia($a_xml_writer, $media);
			$a_xml_writer->xmlEndTag("prtpartiallycorrect");

			$a_xml_writer->xmlStartTag("prtincorrect", array("format" => "html"));
			$media = $this->getRTEMedia($question->prt_incorrect);
			$this->addRTEText($a_xml_writer, $question->prt_incorrect);
			$this->addRTEMedia($a_xml_writer, $media);
			$a_xml_writer->xmlEndTag("prtincorrect");

			$a_xml_writer->xmlElement("variantsselectionseed", NULL, $question->variants_selection_seed);

			$options = $question->options;

			$a_xml_writer->xmlElement('questionsimplify', NULL, (int)$options->get_option('simplify'));

			$a_xml_writer->xmlElement('assumepositive', NULL, (int)$options->get_option('assumepos'));

			$a_xml_writer->xmlElement('assumereal', NULL, (int)$options->get_option('assumereal'));

			$a_xml_writer->xmlElement('multiplicationsign', NULL, (string)$options->get_option('multiplicationsign'));

			$a_xml_writer->xmlElement('sqrtsign', NULL, $options->get_option('sqrtsign'));

			$a_xml_writer->xmlElement('complexno', NULL, $options->get_option('complexno'));

			$a_xml_writer->xmlElement("inversetrig", NULL, $options->get_option('inversetrig'));

			$a_xml_writer->xmlElement("matrixparens", NULL, $options->get_option('matrixparens'));

			$a_xml_writer->xmlElement("logicsymbol", NULL, 'lang');

			//Inputs
			if (sizeof($question->inputs)) {
				foreach ($question->inputs as $input) {
					$a_xml_writer->xmlStartTag("input");

					$a_xml_writer->xmlElement("name", NULL, $input->get_name());
					$a_xml_writer->xmlElement("type", NULL, assStackQuestionUtils::_getInputType($input));
					$a_xml_writer->xmlElement("tans", NULL, $input->get_teacher_answer());
					$a_xml_writer->xmlElement("boxsize", NULL, $input->get_parameter('boxWidth'));
					$a_xml_writer->xmlElement("strictsyntax", NULL, (int)$input->get_parameter('strictSyntax'));
					$a_xml_writer->xmlElement("insertstars", NULL, (int)$input->get_parameter('insertStars'));
					$a_xml_writer->xmlElement("syntaxhint", NULL, $input->get_parameter('syntaxHint'));
					$a_xml_writer->xmlElement("forbidwords", NULL, $input->get_parameter('forbidWords'));
					$a_xml_writer->xmlElement("allowwords", NULL, $input->get_parameter('allowWords'));
					$a_xml_writer->xmlElement("forbidfloat", NULL, (int)$input->get_parameter('forbidFloats'));
					$a_xml_writer->xmlElement("requirelowestterms", NULL, (int)$input->get_parameter('lowestTerms'));
					$a_xml_writer->xmlElement("checkanswertype", NULL, (int)$input->get_parameter('sameType'));
					$a_xml_writer->xmlElement("mustverify", NULL, (int)$input->get_parameter('mustVerify'));
					$a_xml_writer->xmlElement("showvalidation", NULL, (int)$input->get_parameter('showValidation'));
					$a_xml_writer->xmlElement("options", NULL, assStackQuestionUtils::_serializeExtraOptions($input->get_extra_options()));

					$a_xml_writer->xmlEndTag("input");
				}
			}


			//PRT
			if (sizeof($question->prts)) {
				foreach ($question->prts as $prt) {
					$a_xml_writer->xmlStartTag("prt");

					$a_xml_writer->xmlElement("name", NULL, $prt->get_name());
					$a_xml_writer->xmlElement("value", NULL, $prt->get_value());
					$a_xml_writer->xmlElement("autosimplify", NULL, (int)$prt->isSimplify());

					$a_xml_writer->xmlStartTag("feedbackvariables", array("format" => "html"));
					$a_xml_writer->xmlElement("text", NULL, $prt->get_feedbackvariables_keyvals());
					$a_xml_writer->xmlEndTag("feedbackvariables");

					//Nodes
					if (sizeof($prt->getNodes())) {
						foreach ($prt->getNodes() as $node) {

							$branches_info = $node->summarise_branches();
							$feedback_info = $node->getFeedbackFromNode();

							$a_xml_writer->xmlStartTag("node");

							$a_xml_writer->xmlElement("name", NULL, (string)$node->nodeid);
							$a_xml_writer->xmlElement("answertest", NULL, $node->get_test());
							$a_xml_writer->xmlElement("sans", NULL, $node->getRawSans());
							$a_xml_writer->xmlElement("tans", NULL, $node->getRawTans());
							$a_xml_writer->xmlElement("testoptions", NULL, assStackQuestionUtils::_serializeExtraOptions($node->getAtoptions()));
							$a_xml_writer->xmlElement("quiet", NULL, (int)$node->isQuiet());

							$a_xml_writer->xmlElement("truescoremode", NULL, $branches_info->truescoremode);
							$a_xml_writer->xmlElement("truescore", NULL, $branches_info->truescore);
							$a_xml_writer->xmlElement("truepenalty", NULL, $feedback_info['true_penalty']);
							$a_xml_writer->xmlElement("truenextnode", NULL, $branches_info->truenextnode);
							$a_xml_writer->xmlElement("trueanswernote", NULL, $branches_info->truenote);
							$a_xml_writer->xmlElement("truefeedbackformat", NULL, (int)$feedback_info['true_feedback_format']);

							$a_xml_writer->xmlStartTag("truefeedback", array("format" => "html"));
							$media = $this->getRTEMedia($feedback_info['true_feedback']);
							$this->addRTEText($a_xml_writer, $feedback_info['true_feedback']);
							$this->addRTEMedia($a_xml_writer, $media);
							$a_xml_writer->xmlEndTag("truefeedback");

							$a_xml_writer->xmlElement("falsescoremode", NULL, $branches_info->falsescoremode);
							$a_xml_writer->xmlElement("falsescore", NULL, $branches_info->falsescore);
							$a_xml_writer->xmlElement("falsepenalty", NULL, $feedback_info['false_penalty']);
							$a_xml_writer->xmlElement("falsenextnode", NULL, $branches_info->falsenextnode);
							$a_xml_writer->xmlElement("falseanswernote", NULL, $branches_info->falsenote);
							$a_xml_writer->xmlElement("falsefeedbackformat", NULL, (int)$feedback_info['false_feedback_format']);


							$a_xml_writer->xmlStartTag("falsefeedback", array("format" => "html"));
							$media = $this->getRTEMedia($feedback_info['false_feedback']);
							$this->addRTEText($a_xml_writer, $feedback_info['false_feedback']);
							$this->addRTEMedia($a_xml_writer, $media);
							$a_xml_writer->xmlEndTag("falsefeedback");

							$a_xml_writer->xmlEndTag("node");
						}
					}

					$a_xml_writer->xmlEndTag("prt");
				}

			}

			//deployed seeds
			if (sizeof($question->deployed_seeds)) {
				foreach ($question->deployed_seeds as $seed) {
					$a_xml_writer->xmlElement("deployedseed", NULL, $seed);
				}
			}


			//tests
			if (isset($question->getUnitTests()['test_cases'])) {
				foreach ($question->getUnitTests()['test_cases'] as $testcase_name => $test_case) {
					$a_xml_writer->xmlStartTag("qtest");
					$a_xml_writer->xmlElement("testcase", NULL, $testcase_name);

					//test input
					foreach ($test_case['inputs'] as $input_name => $input) {
						if (isset($input['value'])) {
							$a_xml_writer->xmlStartTag("testinput");
							$a_xml_writer->xmlElement("name", NULL, $input_name);
							$a_xml_writer->xmlElement("value", NULL, $input['value']);
							$a_xml_writer->xmlEndTag("testinput");
						}
					}

					//test expected
					foreach ($test_case['expected'] as $prt_name => $expected) {
						if (isset($expected['score']) and isset($expected['penalty']) and isset($expected['answer_note'])) {
							$a_xml_writer->xmlStartTag("expected");
							$a_xml_writer->xmlElement("name", NULL, $prt_name);
							$a_xml_writer->xmlElement("expectedscore", NULL, $expected['score']);
							$a_xml_writer->xmlElement("expectedpenalty", NULL, $expected['penalty']);
							$a_xml_writer->xmlElement("expectedanswernote", NULL, $expected['answer_note']);
							$a_xml_writer->xmlEndTag("expected");
						}
					}
					$a_xml_writer->xmlEndTag("qtest");
				}
			}


			$a_xml_writer->xmlEndTag("question");
		}

		$a_xml_writer->xmlEndTag("quiz");

		$xml = $a_xml_writer->xmlDumpMem(FALSE);

		if (is_array($this->getStackQuestions())) {
			if (sizeof($this->getStackQuestions()) > 1) {
				ilUtil::deliverData($xml, "stack_question_" . $question_id . "_and_others.xml", "xml");
			} elseif
			(sizeof($this->getStackQuestions()) == 1) {
				ilUtil::deliverData($xml, "stack_question_" . $question_id . ".xml", "xml");
			}
		}
	}

	/**
	 * Get the media files used in an RTE text
	 * @param string        text to analyze
	 * @param assStackQuestion question
	 * @return    array        name => file content
	 */
	private function getRTEMedia($a_text, $stack_question = ""): array
	{
		$media = array();
		$matches = array();
		preg_match_all('/src=".*\/mobs\/mm_([0-9]+)\/([^"]+)"/', $a_text, $matches);

		for ($i = 0; $i < count($matches[0]); $i++) {
			$id = $matches[1][$i];
			$name = $matches[2][$i];

			$new_match = explode('?', $name);

			if (is_file(ilUtil::getWebspaceDir() . "/mobs/mm_" . $id . '/' . $new_match[0])) {
				$media[$new_match[0]] = file_get_contents(ilUtil::getWebspaceDir() . "/mobs/mm_" . $id . '/' . $new_match[0]);
			}
		}

		return $media;
	}

	/**
	 * Add an RTE text
	 * This will change the media references and wrap the text in CDATA
	 * @param ilXmlWriter    XML writer
	 * @param string        text to add
	 * @param string        tag for the element
	 * @param array        attributes
	 */
	private
	function addRTEText($a_xml_writer, $a_text, $a_tag = 'text', $a_attr = null)
	{
		$text = preg_replace('/src=".*\/mobs\/mm_([0-9]+)\/([^"]+)"/', 'src="@@PLUGINFILE@@/$2"', $a_text);

		$text = '<![CDATA[' . $text . ']]>';

		$a_xml_writer->xmlElement($a_tag, NULL, $text, false, false);
	}


	/**
	 * Add media files as <file> elements
	 * @param ilXmlWriter        XML writer
	 * @param array            name => content
	 * @param string            tag for the element
	 */
	private
	function addRTEMedia($a_xml_writer, $a_media, $a_tag = 'file')
	{
		foreach ($a_media as $name => $content) {
			$attr = array('name' => $name, 'path' => '/', 'encoding' => 'base64');
			$a_xml_writer->xmlElement('file', $attr, base64_encode($content), false, false);
		}
	}
} 