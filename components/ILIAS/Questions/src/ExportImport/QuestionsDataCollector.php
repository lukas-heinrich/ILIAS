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

namespace ILIAS\Questions\ExportImport;

use ILIAS\Questions\ExportImport\Foundation\Contracts\DataCollector;
use ILIAS\Questions\Persistence\Repository;

class QuestionsDataCollector implements DataCollector
{
    private array $answer_forms = [];
    private bool $example_config = false;

    public function __construct(
        private readonly Repository $repository
    ) {
    }

    public function getQuestions(): \Generator
    {
        foreach ($this->repository->getQuestionDataOnlyForAllQuestions() as $question_data) {
            $question = $this->repository->getForQuestionId($question_data->getId());
            $this->answer_forms = [...$this->answer_forms, ...$question->getAnswerForms()];
            yield $question;
        }
    }

    public function getAnswerForms(): array
    {
        return $this->answer_forms;
    }

    public function withExampleConfig(): self
    {
        $clone = clone $this;
        $clone->example_config = true;
        return $clone;
    }
}
