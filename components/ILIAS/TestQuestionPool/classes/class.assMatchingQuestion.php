<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\TestQuestionPool\Questions\QuestionLMExportable;
use ILIAS\TestQuestionPool\Questions\QuestionAutosaveable;

use ILIAS\Test\Logging\AdditionalInformationGenerator;

use ILIAS\Refinery\Random\Group as RandomGroup;
use ILIAS\Refinery\Random\Seed\RandomSeed;

/**
 * Class for matching questions
 *
 * assMatchingQuestion is a class for matching questions.
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 *
 * @version		$Id$
 *
 * @ingroup		ModulesTestQuestionPool
 */
class assMatchingQuestion extends assQuestion implements ilObjQuestionScoringAdjustable, ilObjAnswerScoringAdjustable, iQuestionCondition, QuestionLMExportable, QuestionAutosaveable
{
    public const MT_TERMS_PICTURES = 0;
    public const MT_TERMS_DEFINITIONS = 1;

    public const MATCHING_MODE_1_ON_1 = '1:1';
    public const MATCHING_MODE_N_ON_N = 'n:n';

    public int $thumb_geometry = 100;
    private int $shufflemode = 0;
    public int $element_height;
    public int $matching_type;
    protected string $matching_mode = self::MATCHING_MODE_1_ON_1;

    private RandomGroup $randomGroup;

    /**
    * The possible matching pairs of the matching question
    *
    * $matchingpairs is an array of the predefined matching pairs of the matching question
    *
    * @var array
    */
    public $matchingpairs;

    /**
    * @var array<assAnswerMatchingTerm>
    */
    protected array $terms = [];

    /**
    * @var array<assAnswerMatchingDefinition>
    */
    protected array $definitions = [];

    /**
     * assMatchingQuestion constructor
     *
     * The constructor takes possible arguments an creates an instance of the assMatchingQuestion object.
     *
     * @param string  $title    A title string to describe the question
     * @param string  $comment  A comment string to describe the question
     * @param string  $author   A string containing the name of the questions author
     * @param integer $owner    A numerical ID to identify the owner/creator
     * @param string  $question The question string of the matching question
     * @param int     $matching_type
     */
    public function __construct(
        $title = "",
        $comment = "",
        $author = "",
        $owner = -1,
        $question = "",
        $matching_type = self::MT_TERMS_DEFINITIONS
    ) {
        global $DIC;

        parent::__construct($title, $comment, $author, $owner, $question);
        $this->matchingpairs = [];
        $this->matching_type = $matching_type;
        $this->terms = [];
        $this->definitions = [];
        $this->randomGroup = $DIC->refinery()->random();
    }

    public function getShuffleMode(): int
    {
        return $this->shufflemode;
    }

    public function setShuffleMode(int $shuffle)
    {
        $this->shufflemode = $shuffle;
    }

    public function isComplete(): bool
    {
        if (strlen($this->title)
            && $this->author
            && $this->question
            && count($this->matchingpairs)
            && $this->getMaximumPoints() > 0
        ) {
            return true;
        }
        return false;
    }

    public function saveToDb(?int $original_id = null): void
    {
        $this->saveQuestionDataToDb($original_id);
        $this->saveAdditionalQuestionDataToDb();
        $this->saveAnswerSpecificDataToDb();

        parent::saveToDb();
    }

    public function saveAnswerSpecificDataToDb()
    {
        $this->rebuildThumbnails();

        $this->db->manipulateF(
            "DELETE FROM qpl_a_mterm WHERE question_fi = %s",
            [ 'integer' ],
            [ $this->getId() ]
        );

        // delete old definitions
        $this->db->manipulateF(
            "DELETE FROM qpl_a_mdef WHERE question_fi = %s",
            [ 'integer' ],
            [ $this->getId() ]
        );

        $termids = [];
        // write terms
        foreach ($this->terms as $key => $term) {
            $next_id = $this->db->nextId('qpl_a_mterm');
            $this->db->insert('qpl_a_mterm', [
                'term_id' => ['integer', $next_id],
                'question_fi' => ['integer', $this->getId()],
                'picture' => ['text', $term->getPicture()],
                'term' => ['text', $term->getText()],
                'ident' => ['integer', $term->getIdentifier()]
            ]);
            $termids[$term->getIdentifier()] = $next_id;
        }

        $definitionids = [];
        // write definitions
        foreach ($this->definitions as $key => $definition) {
            $next_id = $this->db->nextId('qpl_a_mdef');
            $this->db->insert('qpl_a_mdef', [
                'def_id' => ['integer', $next_id],
                'question_fi' => ['integer', $this->getId()],
                'picture' => ['text', $definition->getPicture()],
                'definition' => ['text', $definition->getText()],
                'ident' => ['integer', $definition->getIdentifier()]
            ]);
            $definitionids[$definition->getIdentifier()] = $next_id;
        }

        $this->db->manipulateF(
            "DELETE FROM qpl_a_matching WHERE question_fi = %s",
            [ 'integer' ],
            [ $this->getId() ]
        );
        $matchingpairs = $this->getMatchingPairs();
        foreach ($matchingpairs as $key => $pair) {
            $next_id = $this->db->nextId('qpl_a_matching');
            $this->db->manipulateF(
                "INSERT INTO qpl_a_matching (answer_id, question_fi, points, term_fi, definition_fi) VALUES (%s, %s, %s, %s, %s)",
                [ 'integer', 'integer', 'float', 'integer', 'integer' ],
                [
                                    $next_id,
                                    $this->getId(),
                                    $pair->getPoints(),
                                    $termids[$pair->getTerm()->getIdentifier()],
                                    $definitionids[$pair->getDefinition()->getIdentifier()]
                                ]
            );
        }
    }

    public function saveAdditionalQuestionDataToDb()
    {
        $this->db->manipulateF(
            "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
            [ "integer" ],
            [ $this->getId() ]
        );

        $this->db->insert($this->getAdditionalTableName(), [
            'question_fi' => ['integer', $this->getId()],
            'shuffle' => ['text', $this->getShuffleMode()],
            'matching_type' => ['text', $this->matching_type],
            'thumb_geometry' => ['integer', $this->getThumbGeometry()],
            'matching_mode' => ['text', $this->getMatchingMode()]
        ]);
    }

    /**
    * Loads a assMatchingQuestion object from a database
    *
    * @param object $db A pear DB object
    * @param integer $question_id A unique key which defines the multiple choice test in the database
    */
    public function loadFromDb($question_id): void
    {
        $query = "
			SELECT		qpl_questions.*,
						{$this->getAdditionalTableName()}.*
			FROM		qpl_questions
			LEFT JOIN	{$this->getAdditionalTableName()}
			ON			{$this->getAdditionalTableName()}.question_fi = qpl_questions.question_id
			WHERE		qpl_questions.question_id = %s
		";

        $result = $this->db->queryF(
            $query,
            ['integer'],
            [$question_id]
        );

        if ($result->numRows() == 1) {
            $data = $this->db->fetchAssoc($result);
            $this->setId((int) $question_id);
            $this->setObjId((int) $data["obj_fi"]);
            $this->setTitle((string) $data["title"]);
            $this->setComment((string) $data["description"]);
            $this->setOriginalId((int) $data["original_id"]);
            $this->setNrOfTries((int) $data['nr_of_tries']);
            $this->setAuthor($data["author"]);
            $this->setPoints((float) $data["points"]);
            $this->setOwner((int) $data["owner"]);
            $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc((string) $data["question_text"], 1));
            $this->setThumbGeometry((int) $data["thumb_geometry"]);
            $this->setShuffle($data["shuffle"] != '0');
            $this->setShuffleMode((int) $data['shuffle']);
            $this->setMatchingMode($data['matching_mode'] === null ? self::MATCHING_MODE_1_ON_1 : $data['matching_mode']);

            try {
                $this->setLifecycle(ilAssQuestionLifecycle::getInstance($data['lifecycle']));
            } catch (ilTestQuestionPoolInvalidArgumentException $e) {
                $this->setLifecycle(ilAssQuestionLifecycle::getDraftInstance());
            }

            try {
                $this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
            } catch (ilTestQuestionPoolException $e) {
            }
        }

        $termids = [];
        $result = $this->db->queryF(
            "SELECT * FROM qpl_a_mterm WHERE question_fi = %s ORDER BY term_id ASC",
            ['integer'],
            [$question_id]
        );
        $this->terms = [];
        if ($result->numRows() > 0) {
            while ($data = $this->db->fetchAssoc($result)) {
                $term = $this->createMatchingTerm($data['term'] ?? '', $data['picture'] ?? '', (int) $data['ident']);
                $this->terms[] = $term;
                $termids[$data['term_id']] = $term;
            }
        }

        $definitionids = [];
        $result = $this->db->queryF(
            "SELECT * FROM qpl_a_mdef WHERE question_fi = %s ORDER BY def_id ASC",
            ['integer'],
            [$question_id]
        );

        $this->definitions = [];
        if ($result->numRows() > 0) {
            while ($data = $this->db->fetchAssoc($result)) {
                $definition = $this->createMatchingDefinition($data['definition'] ?? '', $data['picture'] ?? '', (int) $data['ident']);
                array_push($this->definitions, $definition);
                $definitionids[$data['def_id']] = $definition;
            }
        }

        $this->matchingpairs = [];
        $result = $this->db->queryF(
            "SELECT * FROM qpl_a_matching WHERE question_fi = %s ORDER BY answer_id",
            ['integer'],
            [$question_id]
        );
        if ($result->numRows() > 0) {
            while ($data = $this->db->fetchAssoc($result)) {
                $pair = $this->createMatchingPair(
                    $termids[$data['term_fi']],
                    $definitionids[$data['definition_fi']],
                    (float) $data['points']
                );
                array_push($this->matchingpairs, $pair);
            }
        }
        parent::loadFromDb((int) $question_id);
    }

    protected function cloneQuestionTypeSpecificProperties(
        \assQuestion $target
    ): \assQuestion {
        $target->cloneImages($this->getId(), $this->getObjId(), $target->getId(), $target->getObjId());
        return $target;
    }

    private function cloneImages(
        int $source_question_id,
        int $source_parent_id,
        int $target_question_id,
        int $target_parent_id
    ): void {
        $image_source_path = $this->getImagePath($source_question_id, $source_parent_id);
        $image_target_path = $this->getImagePath($target_question_id, $target_parent_id);

        if (!file_exists($image_target_path)) {
            ilFileUtils::makeDirParents($image_target_path);
        } else {
            $this->removeAllImageFiles($image_target_path);
        }

        foreach ($this->terms as $term) {
            if ($term->getPicture() === '') {
                continue;
            }

            $filename = $term->getPicture();
            if (!file_exists($image_source_path . $filename, $image_target_path . $filename)
                || !copy($image_source_path . $filename, $image_target_path . $filename)) {
                $this->log->root()->warning('matching question image could not be copied: '
                    . $image_source_path . $filename);
            }
            if (!file_exists($image_source_path . $this->getThumbPrefix() . $filename)
                || !copy(
                    $image_source_path . $this->getThumbPrefix() . $filename,
                    $image_target_path . $this->getThumbPrefix() . $filename
                )) {
                $this->log->root()->warning('matching question image thumbnail could not be copied: '
                    . $image_source_path . $this->getThumbPrefix() . $filename);
            }
        }
        foreach ($this->definitions as $definition) {
            if ($definition->getPicture() === '') {
                continue;
            }
            $filename = $definition->getPicture();

            if (!file_exists($image_source_path . $filename)
                || !copy($image_source_path . $filename, $image_target_path . $filename)) {
                $this->log->root()->warning('matching question image could not be copied: '
                    . $image_source_path . $filename);
            }

            if (!file_exists($image_source_path . $this->getThumbPrefix() . $filename)
                || !copy(
                    $image_source_path . $this->getThumbPrefix() . $filename,
                    $image_target_path . $this->getThumbPrefix() . $filename
                )) {
                $this->log->root()->warning('matching question image thumbnail could not be copied: '
                    . $image_source_path . $this->getThumbPrefix() . $filename);
            }
        }
    }

    /**
    * Inserts a matching pair for an matching choice question. The students have to fill in an order for the matching pair.
    * The matching pair is an ASS_AnswerMatching object that will be created and assigned to the array $this->matchingpairs.
    *
    * @param integer $position The insert position in the matching pairs array
    * @param object $term A matching term
    * @param object $definition A matching definition
    * @param double $points The points for selecting the matching pair (even negative points can be used)
    * @see $matchingpairs
    */
    public function insertMatchingPair($position, $term = null, $definition = null, $points = 0.0): void
    {
        $pair = $this->createMatchingPair($term, $definition, $points);

        if ($position < count($this->matchingpairs)) {
            $part1 = array_slice($this->matchingpairs, 0, $position);
            $part2 = array_slice($this->matchingpairs, $position);
            $this->matchingpairs = array_merge($part1, [$pair], $part2);
        } else {
            array_push($this->matchingpairs, $pair);
        }
    }

    /**
     * Adds an matching pair for an matching choice question. The students have to fill in an order for the
     * matching pair. The matching pair is an ASS_AnswerMatching object that will be created and assigned to the
     * array $this->matchingpairs.
     *
     * @param assAnswerMatchingTerm|null		$term       A matching term
     * @param assAnswerMatchingDefinition|null	$definition A matching definition
     * @param float 							$points     The points for selecting the matching pair, incl. negative.
     *
     * @see $matchingpairs
     */
    public function addMatchingPair(assAnswerMatchingTerm $term = null, assAnswerMatchingDefinition $definition = null, $points = 0.0): void
    {
        $pair = $this->createMatchingPair($term, $definition, $points);
        array_push($this->matchingpairs, $pair);
    }

    /**
    * Returns a term with a given identifier
    */
    public function getTermWithIdentifier($a_identifier)
    {
        foreach ($this->terms as $term) {
            if ($term->getIdentifier() == $a_identifier) {
                return $term;
            }
        }
        return null;
    }

    /**
    * Returns a definition with a given identifier
    */
    public function getDefinitionWithIdentifier($a_identifier)
    {
        foreach ($this->definitions as $definition) {
            if ($definition->getIdentifier() == $a_identifier) {
                return $definition;
            }
        }
        return null;
    }

    /**
    * Returns a matching pair with a given index. The index of the first
    * matching pair is 0, the index of the second matching pair is 1 and so on.
    *
    * @param integer $index A nonnegative index of the n-th matching pair
    * @return object ASS_AnswerMatching-Object
    * @see $matchingpairs
    */
    public function getMatchingPair($index = 0): ?object
    {
        if ($index < 0) {
            return null;
        }
        if (count($this->matchingpairs) < 1) {
            return null;
        }
        if ($index >= count($this->matchingpairs)) {
            return null;
        }
        return $this->matchingpairs[$index];
    }

    /**
    * Deletes a matching pair with a given index. The index of the first
    * matching pair is 0, the index of the second matching pair is 1 and so on.
    *
    * @param integer $index A nonnegative index of the n-th matching pair
    * @see $matchingpairs
    */
    public function deleteMatchingPair($index = 0): void
    {
        if ($index < 0) {
            return;
        }
        if (count($this->matchingpairs) < 1) {
            return;
        }
        if ($index >= count($this->matchingpairs)) {
            return;
        }
        unset($this->matchingpairs[$index]);
        $this->matchingpairs = array_values($this->matchingpairs);
    }

    /**
    * Deletes all matching pairs
    * @see $matchingpairs
    */
    public function flushMatchingPairs(): void
    {
        $this->matchingpairs = [];
    }

    /**
    * @param assAnswerMatchingPair[]
    */
    public function withMatchingPairs(array $pairs): self
    {
        $clone = clone $this;
        $clone->matchingpairs = $pairs;
        return $clone;
    }


    /**
    * Returns the number of matching pairs
    *
    * @return integer The number of matching pairs of the matching question
    * @see $matchingpairs
    */
    public function getMatchingPairCount(): int
    {
        return count($this->matchingpairs);
    }

    /**
     * Returns the terms of the matching question
     *
     * @return assAnswerMatchingTerm[] An array containing the terms
     * @see $terms
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
    * Returns the definitions of the matching question
    *
    * @return array An array containing the definitions
    * @see $terms
    */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
    * Returns the number of terms
    *
    * @return integer The number of terms
    * @see $terms
    */
    public function getTermCount(): int
    {
        return count($this->terms);
    }

    /**
    * Returns the number of definitions
    *
    * @return integer The number of definitions
    * @see $definitions
    */
    public function getDefinitionCount(): int
    {
        return count($this->definitions);
    }

    public function addTerm(assAnswerMatchingTerm $term): void
    {
        $this->terms[] = $term;
    }

    /**
    * Adds a definition
    *
    * @param object $definition The definition
    * @see $definitions
    */
    public function addDefinition($definition): void
    {
        array_push($this->definitions, $definition);
    }

    /**
    * Inserts a term
    *
    * @param string $term The text of the term
    * @see $terms
    */
    public function insertTerm($position, assAnswerMatchingTerm $term = null): void
    {
        if (is_null($term)) {
            $term = $this->createMatchingTerm();
        }
        if ($position < count($this->terms)) {
            $part1 = array_slice($this->terms, 0, $position);
            $part2 = array_slice($this->terms, $position);
            $this->terms = array_merge($part1, [$term], $part2);
        } else {
            array_push($this->terms, $term);
        }
    }

    /**
    * Inserts a definition
    *
    * @param object $definition The definition
    * @see $definitions
    */
    public function insertDefinition($position, assAnswerMatchingDefinition $definition = null): void
    {
        if (is_null($definition)) {
            $definition = $this->createMatchingDefinition();
        }
        if ($position < count($this->definitions)) {
            $part1 = array_slice($this->definitions, 0, $position);
            $part2 = array_slice($this->definitions, $position);
            $this->definitions = array_merge($part1, [$definition], $part2);
        } else {
            array_push($this->definitions, $definition);
        }
    }

    /**
    * Deletes all terms
    * @see $terms
    */
    public function flushTerms(): void
    {
        $this->terms = [];
    }

    /**
    * Deletes all definitions
    * @see $definitions
    */
    public function flushDefinitions(): void
    {
        $this->definitions = [];
    }

    /**
    * Deletes a term
    *
    * @param string $term_id The id of the term to delete
    * @see $terms
    */
    public function deleteTerm($position): void
    {
        unset($this->terms[$position]);
        $this->terms = array_values($this->terms);
    }

    /**
    * Deletes a definition
    *
    * @param integer $position The position of the definition in the definition array
    * @see $definitions
    */
    public function deleteDefinition($position): void
    {
        unset($this->definitions[$position]);
        $this->definitions = array_values($this->definitions);
    }

    /**
    * Sets a specific term
    *
    * @param string $term The text of the term
    * @param string $index The index of the term
    * @see $terms
    */
    public function setTerm($term, $index): void
    {
        $this->terms[$index] = $term;
    }

    public function calculateReachedPoints(
        int $active_id,
        ?int $pass = null,
        bool $authorized_solution = true
    ): float {
        $found_values = [];
        if (is_null($pass)) {
            $pass = $this->getSolutionMaxPass($active_id);
        }
        $result = $this->getCurrentSolutionResultSet($active_id, (int) $pass, $authorized_solution);
        while ($data = $this->db->fetchAssoc($result)) {
            if ($data['value1'] === '') {
                continue;
            }

            if (!isset($found_values[$data['value2']])) {
                $found_values[$data['value2']] = [];
            }

            $found_values[$data['value2']][] = $data['value1'];
        }

        $points = $this->calculateReachedPointsForSolution($found_values);

        return $points;
    }

    /**
     * Calculates and Returns the maximum points, a learner can reach answering the question
     */
    public function getMaximumPoints(): float
    {
        $points = 0;

        foreach ($this->getMaximumScoringMatchingPairs() as $pair) {
            $points += $pair->getPoints();
        }

        return $points;
    }

    public function getMaximumScoringMatchingPairs(): array
    {
        if ($this->getMatchingMode() == self::MATCHING_MODE_N_ON_N) {
            return $this->getPositiveScoredMatchingPairs();
        } elseif ($this->getMatchingMode() == self::MATCHING_MODE_1_ON_1) {
            return $this->getMostPositiveScoredUniqueTermMatchingPairs();
        }

        return [];
    }

    private function getPositiveScoredMatchingPairs(): array
    {
        $matchingPairs = [];

        foreach ($this->matchingpairs as $pair) {
            if ($pair->getPoints() <= 0) {
                continue;
            }

            $matchingPairs[] = $pair;
        }

        return $matchingPairs;
    }

    private function getMostPositiveScoredUniqueTermMatchingPairs(): array
    {
        $matchingPairsByDefinition = [];

        foreach ($this->matchingpairs as $pair) {
            if ($pair->getPoints() <= 0) {
                continue;
            }

            $defId = $pair->getDefinition()->getIdentifier();

            if (!isset($matchingPairsByDefinition[$defId])) {
                $matchingPairsByDefinition[$defId] = $pair;
            } elseif ($pair->getPoints() > $matchingPairsByDefinition[$defId]->getPoints()) {
                $matchingPairsByDefinition[$defId] = $pair;
            }
        }

        return $matchingPairsByDefinition;
    }

    /**
     * @param array $valuePairs
     * @return array $indexedValues
     */
    public function fetchIndexedValuesFromValuePairs(array $valuePairs): array
    {
        $indexedValues = [];

        foreach ($valuePairs as $valuePair) {
            if (!isset($indexedValues[$valuePair['value2']])) {
                $indexedValues[$valuePair['value2']] = [];
            }

            $indexedValues[$valuePair['value2']][] = $valuePair['value1'];
        }

        return $indexedValues;
    }

    /**
    * Returns the encrypted save filename of a matching picture
    * Images are saved with an encrypted filename to prevent users from
    * cheating by guessing the solution from the image filename
    *
    * @param string $filename Original filename
    * @return string Encrypted filename
    */
    public function getEncryptedFilename($filename): string
    {
        $extension = "";
        if (preg_match("/.*\\.(\\w+)$/", $filename, $matches)) {
            $extension = $matches[1];
        }
        return md5($filename) . "." . $extension;
    }

    public function removeTermImage($index): void
    {
        $term = $this->terms[$index] ?? null;
        if (is_object($term)) {
            $this->deleteImagefile($term->getPicture());
            $term = $term->withPicture('');
        }
    }

    public function removeDefinitionImage($index): void
    {
        $definition = $this->definitions[$index] ?? null;
        if (is_object($definition)) {
            $this->deleteImagefile($definition->getPicture());
            $definition = $definition->withPicture('');
        }
    }


    /**
    * Deletes an imagefile from the system if the file is deleted manually
    *
    * @param string $filename Image file filename
    * @return boolean Success
    */
    public function deleteImagefile(string $filename): bool
    {
        $deletename = $filename;
        try {
            $result = unlink($this->getImagePath() . $deletename)
                && unlink($this->getImagePath() . $this->getThumbPrefix() . $deletename);
        } catch (Throwable $e) {
            $result = false;
        }
        return $result;
    }

    public function setImageFile(
        string $image_tempfilename,
        string $image_filename,
        string $previous_filename = ''
    ): bool {
        $result = true;
        if ($image_tempfilename === '') {
            return true;
        }

        $image_filename = str_replace(' ', '_', $image_filename);
        $imagepath = $this->getImagePath();
        if (!file_exists($imagepath)) {
            ilFileUtils::makeDirParents($imagepath);
        }

        if (!ilFileUtils::moveUploadedFile(
            $image_tempfilename,
            $image_filename,
            $imagepath . $image_filename
        )
        ) {
            return false;
        }

        // create thumbnail file
        $thumbpath = $imagepath . $this->getThumbPrefix() . $image_filename;
        ilShellUtil::convertImage(
            $imagepath . $image_filename,
            $thumbpath,
            'JPEG',
            (string) $this->getThumbGeometry()
        );

        if ($result
            && $image_filename !== $previous_filename
            && $previous_filename !== ''
        ) {
            $this->deleteImagefile($previous_filename);
        }
        return $result;
    }

    private function fetchSubmittedMatchingsFromPost(): array
    {
        $post = $this->questionpool_request->getParsedBody();

        $matchings = [];
        if (array_key_exists('matching', $post)) {
            $postData = $post['matching'][$this->getId()];
            foreach ($this->getDefinitions() as $definition) {
                if (isset($postData[$definition->getIdentifier()])) {
                    foreach ($this->getTerms() as $term) {
                        if (isset($postData[$definition->getIdentifier()][$term->getIdentifier()])) {
                            if (!is_array($postData[$definition->getIdentifier()])) {
                                $postData[$definition->getIdentifier()] = [];
                            }
                            $matchings[$definition->getIdentifier()][] = $term->getIdentifier();
                        }
                    }
                }
            }
        }

        return $matchings;
    }

    private function checkSubmittedMatchings(array $submitted_matchings): bool
    {
        if ($this->getMatchingMode() == self::MATCHING_MODE_N_ON_N) {
            return true;
        }

        $handledTerms = [];

        foreach ($submitted_matchings as $terms) {
            if (count($terms) > 1) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt("multiple_matching_values_selected"), true);
                return false;
            }

            foreach ($terms as $i => $term) {
                if (isset($handledTerms[$term])) {
                    $this->tpl->setOnScreenMessage('failure', $this->lng->txt("duplicate_matching_values_selected"), true);
                    return false;
                }

                $handledTerms[$term] = $term;
            }
        }

        return true;
    }

    public function saveWorkingData(
        int $active_id,
        ?int $pass = null,
        bool $authorized = true
    ): bool {
        if ($pass === null) {
            $pass = ilObjTest::_getPass($active_id);
        }

        $submitted_matchings = $this->fetchSubmittedMatchingsFromPost();
        if (!$this->checkSubmittedMatchings($submitted_matchings)) {
            return false;
        }

        $this->getProcessLocker()->executeUserSolutionUpdateLockOperation(
            function () use ($submitted_matchings, $active_id, $pass, $authorized) {
                $this->removeCurrentSolution($active_id, $pass, $authorized);
                foreach ($submitted_matchings as $definition => $terms) {
                    foreach ($terms as $i => $term) {
                        $this->saveCurrentSolution($active_id, $pass, $term, $definition, $authorized);
                    }
                }
            }
        );

        return true;
    }

    protected function savePreviewData(ilAssQuestionPreviewSession $previewSession): void
    {
        $submitted_matchings = $this->fetchSubmittedMatchingsFromPost();

        if ($this->checkSubmittedMatchings($submitted_matchings)) {
            $previewSession->setParticipantsSolution($submitted_matchings);
        }
    }

    public function getRandomId(): int
    {
        mt_srand((float) microtime() * 1000000);
        $random_number = mt_rand(1, 100000);
        $found = false;
        while ($found) {
            $found = false;
            foreach ($this->matchingpairs as $key => $pair) {
                if (($pair->getTerm()->getIdentifier() == $random_number) || ($pair->getDefinition()->getIdentifier() == $random_number)) {
                    $found = true;
                    $random_number++;
                }
            }
        }
        return $random_number;
    }

    public function setShuffle($shuffle = true): void
    {
        $this->shuffle = (bool) $shuffle;
    }

    /**
    * Returns the question type of the question
    *
    * @return integer The question type of the question
    */
    public function getQuestionType(): string
    {
        return "assMatchingQuestion";
    }

    public function getAdditionalTableName(): string
    {
        return "qpl_qst_matching";
    }

    public function getAnswerTableName(): array
    {
        return ["qpl_a_matching", "qpl_a_mterm"];
    }

    /**
    * Collects all text in the question which could contain media objects
    * which were created with the Rich Text Editor
    */
    public function getRTETextWithMediaObjects(): string
    {
        return parent::getRTETextWithMediaObjects();
    }

    /**
    * Returns the matchingpairs array
    */
    public function &getMatchingPairs(): array
    {
        return $this->matchingpairs;
    }

    /**
     * {@inheritdoc}
     */
    public function setExportDetailsXLSX(ilAssExcelFormatHelper $worksheet, int $startrow, int $col, int $active_id, int $pass): int
    {
        parent::setExportDetailsXLSX($worksheet, $startrow, $col, $active_id, $pass);

        $solutions = $this->getSolutionValues($active_id, $pass);

        $imagepath = $this->getImagePath();
        $i = 1;
        foreach ($solutions as $solution) {
            $matches_written = false;
            foreach ($this->getMatchingPairs() as $idx => $pair) {
                if (!$matches_written) {
                    $worksheet->setCell($startrow + $i, $col + 1, $this->lng->txt("matches"));
                }
                $matches_written = true;
                if ($pair->getDefinition()->getIdentifier() == $solution["value2"]) {
                    if (strlen($pair->getDefinition()->getText())) {
                        $worksheet->setCell($startrow + $i, $col, $pair->getDefinition()->getText());
                    } else {
                        $worksheet->setCell($startrow + $i, $col, $pair->getDefinition()->getPicture());
                    }
                }
                if ($pair->getTerm()->getIdentifier() == $solution["value1"]) {
                    if (strlen($pair->getTerm()->getText())) {
                        $worksheet->setCell($startrow + $i, $col + 2, $pair->getTerm()->getText());
                    } else {
                        $worksheet->setCell($startrow + $i, $col + 2, $pair->getTerm()->getPicture());
                    }
                }
            }
            $i++;
        }

        return $startrow + $i + 1;
    }

    /**
    * Get the thumbnail geometry
    *
    * @return integer Geometry
    */
    public function getThumbGeometry(): int
    {
        return $this->thumb_geometry;
    }

    /**
    * Get the thumbnail geometry
    *
    * @return integer Geometry
    */
    public function getThumbSize(): int
    {
        return $this->getThumbGeometry();
    }

    /**
    * Set the thumbnail geometry
    *
    * @param integer $a_geometry Geometry
    */
    public function setThumbGeometry(int $a_geometry): void
    {
        $this->thumb_geometry = ($a_geometry < 1) ? 100 : $a_geometry;
    }

    /**
    * Rebuild the thumbnail images with a new thumbnail size
    */
    public function rebuildThumbnails(): void
    {
        $new_terms = [];
        foreach ($this->terms as $term) {
            if ($term->getPicture() !== '') {
                $current_file_path = $this->getImagePath() . $term->getPicture();
                if (!file_exists($current_file_path)) {
                    $new_terms[] = $term;
                    continue;
                }
                $new_file_name = $this->buildHashedImageFilename($term->getPicture(), true);
                $new_file_path = $this->getImagePath() . $new_file_name;
                rename($current_file_path, $new_file_path);
                $term = $term->withPicture($new_file_name);
                $this->generateThumbForFile($this->getImagePath(), $term->getPicture());
            }
            $new_terms[] = $term;
        }
        $this->terms = $new_terms;

        $new_definitions = [];
        foreach ($this->definitions as $definition) {
            if ($definition->getPicture() !== '') {
                $current_file_path = $this->getImagePath() . $definition->getPicture();
                if (!file_exists($current_file_path)) {
                    $new_definitions[] = $definition;
                    continue;
                }
                $new_file_name = $this->buildHashedImageFilename($definition->getPicture(), true);
                $new_file_path = $this->getImagePath() . $new_file_name;
                rename($current_file_path, $new_file_path);
                $definition = $definition->withPicture($new_file_name);
                $this->generateThumbForFile($this->getImagePath(), $definition->getPicture());
            }
            $new_definitions[] = $definition;
        }
        $this->definitions = $new_definitions;
    }

    public function getThumbPrefix(): string
    {
        return "thumb.";
    }

    protected function generateThumbForFile($path, $file): void
    {
        $filename = $path . $file;
        if (file_exists($filename)) {
            $thumbpath = $path . $this->getThumbPrefix() . $file;
            $path_info = pathinfo($filename);
            $ext = "";
            switch (strtoupper($path_info['extension'])) {
                case 'PNG':
                    $ext = 'PNG';
                    break;
                case 'GIF':
                    $ext = 'GIF';
                    break;
                default:
                    $ext = 'JPEG';
                    break;
            }
            ilShellUtil::convertImage($filename, $thumbpath, $ext, (string) $this->getThumbGeometry());
        }
    }

    /**
    * Returns a JSON representation of the question
    */
    public function toJSON(): string
    {
        $result = [];

        $result['id'] = $this->getId();
        $result['type'] = (string) $this->getQuestionType();
        $result['title'] = $this->getTitle();
        $result['question'] = $this->formatSAQuestion($this->getQuestion());
        $result['nr_of_tries'] = $this->getNrOfTries();
        $result['matching_mode'] = $this->getMatchingMode();
        $result['shuffle'] = true;
        $result['feedback'] = [
            'onenotcorrect' => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false)),
            'allcorrect' => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true))
        ];

        $this->setShuffler($this->randomGroup->shuffleArray(new RandomSeed()));

        $terms = [];
        foreach ($this->getShuffler()->transform($this->getTerms()) as $term) {
            $terms[] = [
                "text" => $this->formatSAQuestion($term->getText()),
                "id" => $this->getId() . $term->getIdentifier()
            ];
        }
        $result['terms'] = $terms;

        $definitions = [];
        foreach ($this->getShuffler()->transform($this->getDefinitions()) as $def) {
            $definitions[] = [
                "text" => $this->formatSAQuestion((string) $def->getText()),
                "id" => $this->getId() . $def->getIdentifier()
            ];
        }
        $result['definitions'] = $definitions;

        // #10353
        $matchings = [];
        foreach ($this->getMatchingPairs() as $pair) {
            // fau: fixLmMatchingPoints - ignore matching pairs with 0 or negative points
            if ($pair->getPoints() <= 0) {
                continue;
            }
            // fau.

            $pid = $pair->getDefinition()->getIdentifier();
            if ($this->getMatchingMode() == self::MATCHING_MODE_N_ON_N) {
                $pid .= '::' . $pair->getTerm()->getIdentifier();
            }

            if (!isset($matchings[$pid]) || $matchings[$pid]["points"] < $pair->getPoints()) {
                $matchings[$pid] = [
                    "term_id" => $this->getId() . $pair->getTerm()->getIdentifier(),
                    "def_id" => $this->getId() . $pair->getDefinition()->getIdentifier(),
                    "points" => (int) $pair->getPoints()
                ];
            }
        }

        $result['matchingPairs'] = array_values($matchings);

        $mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
        $result['mobs'] = $mobs;

        $this->lng->loadLanguageModule('assessment');
        $result['reset_button_label'] = $this->lng->txt("reset_terms");

        return json_encode($result);
    }

    public function setMatchingMode(string $matching_mode): void
    {
        $this->matching_mode = $matching_mode;
    }

    public function getMatchingMode(): string
    {
        return $this->matching_mode;
    }

    protected function calculateReachedPointsForSolution(?array $found_values): float
    {
        $points = 0.0;
        if (!is_array($found_values)) {
            return $points;
        }
        foreach ($found_values as $definition => $terms) {
            if (!is_array($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                foreach ($this->matchingpairs as $pair) {
                    if ($pair->getDefinition()->getIdentifier() == $definition
                        && $pair->getTerm()->getIdentifier() == $term) {
                        $points += $pair->getPoints();
                    }
                }
            }
        }
        return $points;
    }

    public function getOperators(string $expression): array
    {
        return ilOperatorsExpressionMapping::getOperatorsByExpression($expression);
    }

    public function getExpressionTypes(): array
    {
        return [
            iQuestionCondition::PercentageResultExpression,
            iQuestionCondition::NumericResultExpression,
            iQuestionCondition::MatchingResultExpression,
            iQuestionCondition::EmptyAnswerExpression,
        ];
    }

    public function getUserQuestionResult(
        int $active_id,
        int $pass
    ): ilUserQuestionResult {
        $result = new ilUserQuestionResult($this, $active_id, $pass);

        $data = $this->db->queryF(
            "SELECT ident FROM qpl_a_mdef WHERE question_fi = %s ORDER BY def_id",
            ["integer"],
            [$this->getId()]
        );

        $definitions = [];
        for ($index = 1; $index <= $this->db->numRows($data); ++$index) {
            $row = $this->db->fetchAssoc($data);
            $definitions[$row["ident"]] = $index;
        }

        $data = $this->db->queryF(
            "SELECT ident FROM qpl_a_mterm WHERE question_fi = %s ORDER BY term_id",
            ["integer"],
            [$this->getId()]
        );

        $terms = [];
        for ($index = 1; $index <= $this->db->numRows($data); ++$index) {
            $row = $this->db->fetchAssoc($data);
            $terms[$row["ident"]] = $index;
        }

        $maxStep = $this->lookupMaxStep($active_id, $pass);

        if ($maxStep > 0) {
            $data = $this->db->queryF(
                "SELECT value1, value2 FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s AND step = %s",
                ["integer", "integer", "integer","integer"],
                [$active_id, $pass, $this->getId(), $maxStep]
            );
        } else {
            $data = $this->db->queryF(
                "SELECT value1, value2 FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s",
                ["integer", "integer", "integer"],
                [$active_id, $pass, $this->getId()]
            );
        }

        while ($row = $this->db->fetchAssoc($data)) {
            if ($row["value1"] > 0) {
                $result->addKeyValue($definitions[$row["value2"]], $terms[$row["value1"]]);
            }
        }

        $points = $this->calculateReachedPoints($active_id, $pass);
        $max_points = $this->getMaximumPoints();

        $result->setReachedPercentage(($points / $max_points) * 100);

        return $result;
    }

    /**
     * If index is null, the function returns an array with all anwser options
     * Else it returns the specific answer option
     *
     * @param null|int $index
     */
    public function getAvailableAnswerOptions($index = null)
    {
        if ($index !== null) {
            return $this->getMatchingPair($index);
        } else {
            return $this->getMatchingPairs();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function afterSyncWithOriginal(
        int $original_question_id,
        int $clone_question_id,
        int $original_parent_id,
        int $clone_parent_id
    ): void {
        parent::afterSyncWithOriginal($original_question_id, $clone_question_id, $original_parent_id, $clone_parent_id);

        $original_image_path = $this->question_files->buildImagePath($original_question_id, $original_parent_id);
        $clone_image_path = $this->question_files->buildImagePath($clone_question_id, $clone_parent_id);

        ilFileUtils::delDir($original_image_path);
        if (is_dir($clone_image_path)) {
            ilFileUtils::makeDirParents($original_image_path);
            ilFileUtils::rCopy($clone_image_path, $original_image_path);
        }
    }

    protected function createMatchingTerm(string $term = '', string $picture = '', int $identifier = 0): assAnswerMatchingTerm
    {
        return new assAnswerMatchingTerm($term, $picture, $identifier);
    }
    protected function createMatchingDefinition(string $term = '', string $picture = '', int $identifier = 0): assAnswerMatchingDefinition
    {
        return new assAnswerMatchingDefinition($term, $picture, $identifier);
    }
    protected function createMatchingPair(
        assAnswerMatchingTerm $term = null,
        assAnswerMatchingDefinition $definition = null,
        float $points = 0.0
    ): assAnswerMatchingPair {
        $term = $term ?? $this->createMatchingTerm();
        $definition = $definition ?? $this->createMatchingDefinition();
        return new assAnswerMatchingPair($term, $definition, $points);
    }

    public function toLog(AdditionalInformationGenerator $additional_info): array
    {
        $result = [
            AdditionalInformationGenerator::KEY_QUESTION_TYPE => (string) $this->getQuestionType(),
            AdditionalInformationGenerator::KEY_QUESTION_TITLE => $this->getTitle(),
            AdditionalInformationGenerator::KEY_QUESTION_TEXT => $this->formatSAQuestion($this->getQuestion()),
            AdditionalInformationGenerator::KEY_QUESTION_SHUFFLE_ANSWER_OPTIONS => $additional_info
                ->getTrueFalseTagForBool($this->getShuffle()),
            'qpl_qst_inp_matching_mode' => $this->getMatchingMode() === self::MATCHING_MODE_1_ON_1 ? '{{ qpl_qst_inp_matching_mode_one_on_one }}' : '{{ qpl_qst_inp_matching_mode_all_on_all }}',
            AdditionalInformationGenerator::KEY_FEEDBACK => [
                AdditionalInformationGenerator::KEY_QUESTION_FEEDBACK_ON_INCOMPLETE => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false)),
                AdditionalInformationGenerator::KEY_QUESTION_FEEDBACK_ON_COMPLETE => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true))
            ]
        ];

        foreach ($this->getTerms() as $term) {
            $result[AdditionalInformationGenerator::KEY_QUESTION_MATCHING_TERMS][] = $term->getText();
        }

        foreach ($this->getDefinitions() as $definition) {
            $result[AdditionalInformationGenerator::KEY_QUESTION_MATCHING_DEFINITIONS][] = $this->formatSAQuestion((string) $definition->getText());
        }

        // #10353
        $matching_pairs = [];
        $i = 1;
        foreach ($this->getMatchingPairs() as $pair) {
            $matching_pairs[$i++] = [
                AdditionalInformationGenerator::KEY_QUESTION_MATCHING_TERM => $pair->getTerm()->getText(),
                AdditionalInformationGenerator::KEY_QUESTION_MATCHING_DEFINITION => $this->formatSAQuestion((string) $pair->getDefinition()->getText()),
                AdditionalInformationGenerator::KEY_QUESTION_REACHABLE_POINTS => (int) $pair->getPoints()
            ];
        }

        $result[AdditionalInformationGenerator::KEY_QUESTION_CORRECT_ANSWER_OPTIONS] = $matching_pairs;
        return $result;
    }

    public function solutionValuesToLog(
        AdditionalInformationGenerator $additional_info,
        array $solution_values
    ): array {
        $reducer = static function (array $c, assAnswerMatchingTerm|assAnswerMatchingDefinition $v): array {
            $c[$v->getIdentifier()] = $v->getText() !== ''
                ? $v->getPicture()
                : $v->getText();
            return $c;
        };

        $terms_by_identifier = array_reduce(
            $this->getTerms(),
            $reducer,
            []
        );

        $definitions_by_identifier = array_reduce(
            $this->getDefinitions(),
            $reducer,
            []
        );

        return array_map(
            static fn(array $v): string => $definitions_by_identifier['value2']
                . ':' . $terms_by_identifier['value1'],
            $solution_values
        );
    }
}
