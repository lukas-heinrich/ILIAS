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
use ILIAS\Data\UUID\Factory as UuidFactory;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory as Refinery;

class Factory
{
    public function __construct(
        private readonly UuidFactory $uuid_factory,
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
        Properties $properties,
        ClozeText $cloze_text,
        string $legacy_cloze_text,
        ScoringIdentical $scoring_of_identical_responses,
        bool $combinations_enabled
    ): Properties {
        $updated_gaps = $cloze_text->updateGapsFromMarkdown($properties->getGaps());
        return new Properties(
            $properties->getAnswerFormId(),
            $cloze_text->withIdsOfNewGapsInClozeText($updated_gaps->getUndefinedGaps()),
            $updated_gaps,
            $scoring_of_identical_responses,
            $combinations_enabled,
            $legacy_cloze_text
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
