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

use ILIAS\Questions\Question\Definitions\TextMatchingOptions;
use ILIAS\Data\UUID\Uuid;

class Data
{
    public function __construct(
        private readonly Uuid $answer_input_id,
        private ?int $max_chars,
        private ?float $step_size,
        private ?TextMatchingOptions $text_matching_method,
        private ?int $min_autocomplete,
        private ?bool $shuffle_answer_options,
        private ?float $lower_limit,
        private ?float $upper_limit
    ) {
    }

    public function getAnswerInputId(): Uuid
    {
        return $this->answer_input_id;
    }

    public function getMaxChars(): ?int
    {
        return $this->max_chars;
    }

    public function withMaxChars(int $max_chars): self
    {
        $clone = clone $this;
        $clone->max_chars = $max_chars;
        return $clone;
    }

    public function getStepSize(): ?int
    {
        return $this->step_size;
    }

    public function withStepSize(int $step_size): self
    {
        $clone = clone $this;
        $clone->step_size = $step_size;
        return $clone;
    }

    public function getTextMatchingMethod(): ?TextMatchingOptions
    {
        return $this->text_matching_method;
    }

    public function withTextMatchingMethod(TextMatchingOptions $matching_method): self
    {
        $clone = clone $this;
        $clone->text_matching_method = $matching_method;
        return $clone;
    }

    public function getMinAutocomplete(): ?int
    {
        return $this->min_autocomplete;
    }

    public function withMinAutocomplete(int $min_autocomplete): self
    {
        $clone = clone $this;
        $clone->min_autocomplete = $min_autocomplete;
        return $clone;
    }

    public function getShuffleAnswerOptions(): ?bool
    {
        return $this->shuffle_answer_options;
    }

    public function withShuffleAnswerOptions(bool $shuffle_answer_options): self
    {
        $clone = clone $this;
        $clone->shuffle_answer_options = $shuffle_answer_options;
        return $clone;
    }

    public function getLowerLimit(): ?float
    {
        return $this->lower_limit;
    }

    public function withLowerLimit(float $lower_limit): self
    {
        $clone = clone $this;
        $clone->lower_limit = $lower_limit;
        return $clone;
    }

    public function getUpperLimit(): ?float
    {
        return $this->upper_limit;
    }

    public function withUpperLimit(float $upper_limit): self
    {
        $clone = clone $this;
        $clone->upper_limit = $upper_limit;
        return $clone;
    }
}
