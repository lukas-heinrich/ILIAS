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

namespace ILIAS\TestQuestionPool\ExportImport;

use assQuestion;
use Generator;
use ilAssClozeTestFeedback;
use ilAssMultiOptionQuestionFeedback;
use ilAssSpecificFeedbackIdentifierList;
use ILIAS\Data\ObjectId;
use ILIAS\Questions\ExportImport\Foundation\Contracts\DataCollector;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Envelopes\Id;
use ILIAS\Questions\Units\Category;
use ILIAS\Questions\Units\Unit;
use ILIAS\TestQuestionPool\ExportImport\Envelopes\Feedback;
use ILIAS\TestQuestionPool\Questions\GeneralQuestionProperties;
use ILIAS\TestQuestionPool\Questions\GeneralQuestionPropertiesRepository;
use ILIAS\Questions\Units\Repository as UnitsRepository;

/**
 * Collector to aggregate data from the question pool for export.
 */
class QuestionPoolCollector implements DataCollector
{
    /** @var array<int, GeneralQuestionProperties> $questions */
    private ?array $questions = null;

    public function __construct(
        private readonly GeneralQuestionPropertiesRepository $question_repository,
        private readonly UnitsRepository $unit_repository,
        private readonly ObjectId $pool_id
    ) {
    }

    /**
     * Get the ID of the question pool.
     *
     * @return ObjectId
     */
    public function getPoolId(): ObjectId
    {
        return $this->pool_id;
    }

    /**
     * Collect the question properties for all questions in the question pool.
     *
     * @return array<int, GeneralQuestionProperties>
     */
    public function getQuestionProperties(): array
    {
        if ($this->questions === null) {
            $this->questions = $this->question_repository->getForParentObjectId($this->pool_id->toInt());
        }
        return $this->questions;
    }

    /**
     * Collect the question objects for all questions in the question pool.
     *
     * @return Generator<assQuestion>
     */
    public function getQuestionObjects(): Generator
    {
        foreach ($this->getQuestionProperties() as $question) {
            yield assQuestion::instantiateQuestion($question->getQuestionId());
        }
    }

    /*
        Units
    */

    /**
     * Collect the unit categories for all formula questions in the question pool.
     *
     * @return Generator<Category>
     */
    public function getUnitCategories(): Generator
    {
        foreach ($this->getQuestionProperties() as $question) {
            if ($question->getClassName() === 'assFormulaQuestion') {
                yield from $this->unit_repository->getAllUnitCategories($question->getQuestionId());
            }
        }
    }

    /**
     * Collect the units for a given unit category. If no category is provided, it will collect all units for the unit
     * category of the question.
     *
     * @return Generator<Unit>
     */
    public function getUnits(?int $category_id = null): Generator
    {
        if ($category_id !== null) {
            yield from $this->unit_repository->loadUnitsForCategory($category_id);
        }

        foreach ($this->getUnitCategories() as $category) {
            yield from $this->unit_repository->loadUnitsForCategory($category->getId());
        }
    }

    /*
        Feedback
    */

    /**
     * Collect the feedback content for a question and return it as a Feedback transfer object.
     */
    public function getFeedback(assQuestion $question): Feedback
    {
        $feedback = new Feedback(
            new Id($question->getId(), 'question'),
            $question->feedbackOBJ->getGenericFeedbackExportPresentation($question->getId(), false),
            $question->feedbackOBJ->getGenericFeedbackExportPresentation($question->getId(), true),
            $this->loadSpecificFeedback($question),
        );

        return $feedback;
    }

    private function loadSpecificFeedback(assQuestion $question): array
    {
        // Skip if specific feedback is not available or supported by the question type.
        if (
            !$question->feedbackOBJ instanceof ilAssMultiOptionQuestionFeedback ||
            !$question->feedbackOBJ->isSpecificAnswerFeedbackAvailable($question->getId())
        ) {
            return [];
        }

        // Cloze question type specific feedback uses the identifier list to load the answer-specific feedback.
        if ($question->feedbackOBJ instanceof ilAssClozeTestFeedback) {
            $feedback_list = new ilAssSpecificFeedbackIdentifierList();
            $feedback_list->load($question->getId());

            $feedback = [];
            foreach ($feedback_list as $identifier) {
                $feedback[$identifier->getAnswerIndex()] = [
                    'answer_index' => $identifier->getAnswerIndex(),
                    'question_index' => $identifier->getQuestionIndex(),
                    'feedback' => $question->feedbackOBJ->getSpecificAnswerFeedbackExportPresentation(
                        $question->getId(),
                        $identifier->getQuestionIndex(),
                        $identifier->getAnswerIndex()
                    ),
                ];
            }
            return $feedback;
        }

        // Other question types with multiple answer options share the same approach
        foreach (array_keys($question->feedbackOBJ->getAnswerOptionsByAnswerIndex()) as $answer_index) {
            $feedback = [
                'answer_index' => $answer_index,
                'question_index' => 0,
                'feedback' => $question->feedbackOBJ->getSpecificAnswerFeedbackExportPresentation(
                    $question->getId(),
                    0,
                    $answer_index
                ),
            ];
        }
        return $feedback;
    }
}
