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

namespace ILIAS\Questions\AnswerFormTypes\Cloze\Properties\AnswerForm;

use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText\Factory as ClozeTextFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText\Text as ClozeText;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Definitions\ScoringIdentical;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Factory as GapsFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Gaps;
use ILIAS\Data\UUID\Uuid;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory as Refinery;

class Factory
{
    public function __construct(
        private readonly ClozeTextFactory $cloze_text_factory,
        private readonly GapsFactory $gaps_factory
    ) {
    }

    public function fromData(
        ?string $answer_form_id,
        string $cloze_text,
        string $legay_cloze_text,
        array $data
    ): Properties {
        return new Properties(
            $answer_form_id,
            $this->cloze_text_factory->buildFromTextString($cloze_text),
            $legay_cloze_text
        );
    }

    public function fromForm(
        ?string $answer_form_id,
        ClozeText $cloze_text,
        string $legacy_cloze_text,
        ScoringIdentical $scoring_of_identical_responses,
        bool $combinations_enabled
    ): Properties {
        return new Properties(
            $answer_form_id,
            $cloze_text,
            $cloze_text->getGaps(),
            $legacy_cloze_text,
            $scoring_of_identical_responses,
            $combinations_enabled
        );
    }

    public function fromPost(
        Refinery $refinery,
        ArrayBasedRequestWrapper $post_wrapper,
        string $form_input_path,
        ?Uuid $answer_form_id,
        Gaps $gaps,
        string $legacy_cloze_text,
        ScoringIdentical $default_scoring_identical
    ): Properties {
        $cloze_text = $post_wrapper->retrieve(
            $form_input_path . '/' . Properties::FORM_KEY_CLOZE_TEXT,
            $refinery->custom()->transformation(
                fn(string $v): ClozeText => $this->cloze_text_factory->buildFromHiddenInputString($v)
            )
        );

        $scoring_of_identical_responses = $post_wrapper->retrieve(
            $form_input_path . '/' . Properties::FORM_KEY_IDENTICAL_SCORING,
            $refinery->custom()->transformation(
                static fn(string $v): ScoringIdentical => ScoringIdentical::tryFrom($v) ?? $default_scoring_identical
            )
        );

        $combinations_enabled = $post_wrapper->retrieve(
            $form_input_path . '/' . Properties::FORM_KEY_ENABLE_COMBINATIONS,
            $refinery->kindlyTo()->bool()
        );

        return new Properties(
            $answer_form_id,
            $cloze_text,
            $cloze_text->updateGapsFromMarkdown($gaps),
            $legacy_cloze_text,
            $scoring_of_identical_responses,
            $combinations_enabled
        );
    }

    public function buildDefault(): Properties
    {
        return new Properties(
            null,
            $this->cloze_text_factory->buildFromTextString(''),
            $this->gaps_factory->getEmptyGapsObject()
        );
    }
}
