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
use ILIAS\Refinery\Constraint;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Component\Input\Field\Section;

class Gaps
{
    public function __construct(
        private readonly Factory $factory,
        private array $gaps
    ) {
    }

    public function getGapById(Uuid $gap_id): ?Gap
    {
        return $this->gaps[$gap_id->toString()] ?? null;
    }

    public function getGapByTagName(string $tag_name): ?Gap
    {
        return $this->gaps[mb_substr($v['name'], 4)] ?? null;
    }

    public function withNewGap(): self
    {
        $new_gap = $this->factory->getNewGap();
        $clone = clone $this;
        $clone->gaps[$new_gap->getAnswerInputId()] = $new_gap;
        return $clone;
    }

    public function withResetGaps(): self
    {
        if ($this->gaps === []) {
            return self;
        }

        $clone = clone $this;
        $clone->gaps = [];
        return $clone;
    }

    public function getUndefinedGaps(): array
    {
        return array_filter(
            $this->gaps,
            fn(Gap $v): bool => $v instanceof Undefined
        );
    }

    public function buildGapsTypeInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): Section {
        return $ff->section(
            array_reduce(
                $this->gaps,
                function (array $c, Gap $v) use ($ff): array {
                    $c[$v->getAnswerInputId()->toString()] = $ff->select(
                        $v->getShortenedGapName(),
                        $this->gap_factory->getAvailableGapTypesOptionsArray($this->lng)
                    )->withRequired(true);
                    return $c;
                },
                []
            ),
            $lng->txt('select_gap_types')
        )->withAdditionalTransformation(
            $refinery->custom()->transformation(
                fn(array $vs): Gaps => array_reduce(
                    array_keys($vs),
                    fn(Gaps $c, string $v): Gaps => $c->withGap(
                        $this->factory->getGapOfType($vs[$v], $v)
                    ),
                    $this
                )
            )
        );
    }

    public function buildAnswerOptionsInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): Section {
        return $ff->section(
            array_reduce(
                $this->gaps,
                function (array $c, Gap $v) use ($lng, $ff, $refinery): array {
                    $c[$v->getAnswerInputId()->toString()] = $v->getEditSection(
                        $lng,
                        $ff,
                        $refinery
                    );
                    return $c;
                },
                []
            ),
            $this->lng->txt('add_answer_options')
        )->withAdditionalTransformation(

        );
    }
}
