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

namespace ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps;

use ILIAS\Data\UUID\Uuid;
use ILIAS\Language\Language;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Refinery\Constraint;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;

class Select implements Type
{
    private bool $shuffle_answer_options = false;

    public function getIdentifier(): string
    {
        return 'select';
    }

    public function withData(Data $data): self
    {
        if ($data->getShuffleAnswerOptions() === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->shuffle_answer_options = $data->getShuffleAnswerOptions();
        return $clone;
    }

    public function getEditInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): array {
        return [
            'answer_options' => $ff->tag(
                $lng->txt('answer_options'),
                []
            )->withRequired(true)
            ->withValue($this->buildTagsArrayFromAnswerOptions($this->answer_options))
        ];
    }

    public function getEditSectionConstraint(
        Refinery $refinery,
        Language $lng
    ): ?Constraint {
        return null;
    }

    public function getBuildGapTransformation(
        Refinery $refinery,
        Uuid $answer_input_id
    ): Transformation {
        return $refinery->custom()->transformation(
            fn(array $vs): self => new self(
                $answer_input_id,
                $vs['answer_options']
            )
        );
    }

    public function addValuesToData(Data $data): Data
    {
        return $data->withShuffleAnswerOptions($this->shuffle_answer_options);
    }

    public function getAnswerInput(): \ilFormPropertyGUI
    {
        ;
    }
}
