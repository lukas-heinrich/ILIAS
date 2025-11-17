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

use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText\Text;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText\Factory as ClozeTextFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Definitions\ScoringIdentical;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Gaps;
use ILIAS\Questions\Question\Persistence\ManipulateQuery;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Language\Language;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Component\Input\Field\Section;
use ILIAS\UI\Component\Input\Field\Group;

class Properties
{
    public const string FORM_KEY_CLOZE_TEXT = 'cloze_text';
    public const string FORM_KEY_IDENTICAL_SCORING = 'identical_scoring';
    public const string FORM_KEY_ENABLE_COMBINATIONS = 'enable_combinations';
    public const string FORM_KEY_GAPS_TO_EDIT = 'gaps';

    /**
     * @param array<string, \ILIAS\Questions\AnswerFormTypes\Cloze\Gap> $gaps
     */
    public function __construct(
        private readonly ?Uuid $answer_form_id,
        private Text $cloze_text,
        private Gaps $gaps,
        private readonly string $legacy_cloze_text = '',
        private readonly ScoringIdentical $scoring_identical = ScoringIdentical::ScoreAll,
        private readonly bool $combinations_activated = false
    ) {
    }

    public function getAnswerFormId(): ?Uuid
    {
        return $this->answer_form_id;
    }

    public function getClozeText(): Text
    {
        return $this->cloze_text;
    }

    public function withClozeText(Text $cloze_text): self
    {
        $clone = clone $this;
        $clone->cloze_text = $this->cloze_text;
        return $clone;
    }

    public function getLegacyClozeText(): string
    {
        return $this->legacy_cloze_text;
    }

    public function getScoringOfIdenticalResponses(): ScoringIdentical
    {
        return $this->scoring_identical;
    }

    public function areCombinationsActivated(): bool
    {
        return $this->combinations_activated;
    }

    public function getGaps(): Gaps
    {
        return $this->gaps;
    }

    public function withGaps(array $gaps): self
    {
        $clone = clone $this;
        $clone->gaps = $gaps;
        return $clone;
    }

    public function withNewGapsFromClozeText(): self
    {
        $clone = clone $this;
        $clone->gaps = $clone->cloze_text->updateGapsFromMarkdown($this->gaps);
        $clone->cloze_text = $this->addIdsOfNewGapsToClozeText(
            $clone->cloze_text,
            $clone->gaps->getUndefinedGaps()
        );
        return $clone;
    }

    public function buildBasicEditingInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery,
        Factory $propteries_factory,
        ClozeTextFactory $cloze_text_factory
    ): Section {
        return $ff->section(
            [
                self::FORM_KEY_CLOZE_TEXT => $this->getClozeText()->getInput(
                    $lng,
                    $ff,
                    $cloze_text_factory
                ),
                self::FORM_KEY_IDENTICAL_SCORING => ScoringIdentical::buildInput(
                    $lng,
                    $ff,
                    $refinery,
                    $this->scoring_identical
                )->withValue($this->getScoringOfIdenticalResponses()->value),
                self::FORM_KEY_ENABLE_COMBINATIONS => $ff->checkbox($lng->txt('cloze_enable_combinations'))
                    ->withValue($this->areCombinationsActivated())
            ],
            $lng->txt('create_answer_form')
        )->withAdditionalTransformation(
            $refinery->custom()->transformation(
                fn(array $vs): self => $propteries_factory->fromForm(
                    $this,
                    $vs[self::FORM_KEY_CLOZE_TEXT],
                    $this->legacy_cloze_text,
                    $vs[self::FORM_KEY_IDENTICAL_SCORING],
                    $vs[self::FORM_KEY_ENABLE_COMBINATIONS]
                )
            )
        );
    }

    public function buildBasicEditingInputsHidden(
        FieldFactory $ff
    ): Group {
        return $ff->group(
            [
                self::FORM_KEY_CLOZE_TEXT => $this->getClozeText()->getHiddenInput($ff)
                    ->withDedicatedName(self::FORM_KEY_CLOZE_TEXT),
                self::FORM_KEY_GAPS_TO_EDIT => $this->gaps->getHiddenInput($ff)
                    ->withDedicatedName(self::FORM_KEY_GAPS_TO_EDIT),
                self::FORM_KEY_IDENTICAL_SCORING => $ff->hidden()
                    ->withDedicatedName(self::FORM_KEY_IDENTICAL_SCORING)
                    ->withValue($this->getScoringOfIdenticalResponses()->value),
                self::FORM_KEY_ENABLE_COMBINATIONS => $ff->hidden()
                    ->withDedicatedName(self::FORM_KEY_ENABLE_COMBINATIONS)
                    ->withValue($this->areCombinationsActivated() ? 1 : 0)
            ]
        );
    }
}
