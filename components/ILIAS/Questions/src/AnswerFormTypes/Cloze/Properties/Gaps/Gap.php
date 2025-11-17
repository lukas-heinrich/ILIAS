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

use ILIAS\Questions\Question\Persistence\ManipulateQuery;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Language\Language;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Component\Input\Field\Section;

class Gap
{
    public const string GAP_PLACEHOLDER_NAME = 'GAP';

    private const string FORM_KEY_INPUT_ID = 'input_id';
    private const string FORM_KEY_TYPE = 'type';

    public function __construct(
        private Uuid $answer_input_id,
        private ?Type $type = null,
        ?ManipulateQuery $data = null
    ) {
        if ($this->type !== null && $data !== null) {
            $this->type = $this->type->withData($data);
        }
    }

    public function getAnswerInputId(): ?Uuid
    {
        return $this->answer_input_id;
    }

    public function withAnswerInputId(Uuid $answer_input_id): self
    {
        $clone = clone $this;
        $clone->answer_input_id = $answer_input_id;
        return $clone;
    }

    public function isUndefined(): bool
    {
        return $this->type === null;
    }

    public function getGapPlaceholder(): string
    {
        return "{{{$this->buildGapPlaceholderNameWithId()}}}";
    }

    public function buildShortenedGapName(): string
    {
        return self::GAP_PLACEHOLDER_NAME . '_' . mb_substr($this->answer_input_id->toString(), 0, 4);
    }

    public function buildShortenedGapRepresentation(): string
    {
        return "[{$this->buildShortenedGapName()}]";
    }

    public function buildGapPlaceholderNameWithId(): string
    {
        return self::GAP_PLACEHOLDER_NAME . '_' . $this->answer_input_id->toString();
    }

    public function getEditSection(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): Section {
        $section = $ff->section(
            $this->type->getEditInputs($lng, $ff, $refinery),
            "{$this->buildShortenedGapName()} ({$lng->txt("{$this->getIdentifier()}_gap")})"
        );

        $edit_section_constraint = $this->type->getEditSectionConstraint($refinery, $lng);
        if ($edit_section_constraint !== null) {
            $section = $section->withAdditionalTransformation($edit_section_constraint);
        }


        return $section->withAdditionalTransformation(
            $this->type->getBuildGapTransformation($refinery, $this->answer_input_id)
        );
    }

    public function getHiddenInput(
        FieldFactory $ff,
        Refinery $refinery
    ): Group {
        return $ff->group([
            self::FORM_KEY_INPUT_ID => $this->answer_input_id->toString(),
            self::FORM_KEY_TYPE => $this->type?->getIdentifier() ?? ''
        ]);
    }
}
