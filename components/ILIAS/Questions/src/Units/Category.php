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

namespace ILIAS\Questions\Units;

use ILIAS\Language\Language;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Normalizable;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Transformations;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Envelopes\Id;
use ILIAS\Refinery\Transformation;

class Category implements Normalizable
{
    private int $id = 0;
    private string $category = '';
    private int $question_fi = 0;

    public function initFormArray(
        array $data
    ): void {
        $this->id = (int) $data['category_id'];
        $this->category = $data['category'];
        $this->question_fi = (int) $data['question_fi'];
    }

    public function setId(
        int $id
    ): void {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setCategory(
        string $category
    ): void {
        $this->category = $category;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setQuestionFi(
        int $question_fi
    ): void {
        $this->question_fi = $question_fi;
    }

    public function getQuestionFi(): int
    {
        return $this->question_fi;
    }

    public function getDisplayString(
        Language $lng
    ): string {
        $category = $this->getCategory();
        if (strcmp('-qpl_qst_formulaquestion_' . $category . '-', $lng->txt('qpl_qst_formulaquestion_' . $category)) !== 0) {
            $category = $lng->txt('qpl_qst_formulaquestion_' . $category);
        }

        return $category;
    }

    public function toNormalized(Transformations $tt): Transformation
    {
        return $tt->custom()->transformation(fn(): array => [
            'id' => $tt->normalize(new Id($this->id, 'unit_category')),
            'name' => $this->category,
            'question_id' => $tt->normalize(new Id($this->question_fi, 'question')),
        ]);
    }

    public function fromNormalized(Transformations $tt): Transformation
    {
        return $tt->custom()->transformation(function (array $normalized) use ($tt): self {
            $clone = clone $this;
            $clone->id = $tt->denormalize($normalized['id'], Id::class)->getId();
            $clone->category = $tt->string($normalized['name']);
            $clone->question_fi = $tt->denormalize($normalized['question_id'], Id::class)->getId();

            return $clone;
        });
    }
}
