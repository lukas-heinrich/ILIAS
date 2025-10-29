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

namespace ILIAS\Questions\AnswerFormTypes\Cloze;

use ILIAS\Questions\AnswerForm\Type as TypeInterface;
use ILIAS\Questions\AnswerForm\Capabilities\Capability;
use ILIAS\Questions\AnswerFormTypes\Cloze\Views\Edit;
use ILIAS\Questions\AnswerFormTypes\Cloze\Views\Participant;
use ILIAS\Language\Language;

class Type implements TypeInterface
{
    private string $id;
    private ?float $available_points = null;
    private int $image_size = 150;
    private bool $shuffle_answer_options = false;
    private string $cloze_text = '';
    private string $cloze_text_legacy = '';
    private bool $case_sensitive = false;
    private bool $identical_responses_valid = true;
    private ?int $max_chars = null;
    private int $min_autocomplete = 3;

    /**
     *
     * @var array<string, \ILIAS\Questions\AnswerFormTypes\Cloze\Gap>
     */
    private array $gaps = [];

    /**
     * @param array<string, \ILIAS\Questions\AnswerForm\Capabilities\Capability> $available_capabilities
     */
    public function __construct(
        private readonly Persistence $persistence,
        private readonly array $available_capabilities,
        private readonly Edit $edit_view,
        private readonly Participant $participant_view
    ) {
    }

    public function withData(
        string $id,
        ?float $available_points,
        int $image_size,
        bool $shuffle_answer_options,
        string $additional_text,
        string $additional_text_legacy,
        ?array $data
    ): static {
        $this->id = $id;
        $this->available_points = $available_points;
        $this->image_size = $image_size;
        $this->shuffle_answer_options = $shuffle_answer_options;
        $this->cloze_text = $additional_text;
        $this->cloze_text_legacy = $additional_text_legacy;
    }

    public function getAvailablePoints(): ?float
    {
        return $this->available_points;
    }

    public function getImageSize(): int
    {
        return $this->image_size;
    }

    public function getShuffleAnswerOptions(): bool
    {
        return $this->shuffle_answer_options;
    }

    public function getClozeText(): string
    {
        return $this->cloze_text;
    }

    public function getClozeTextLegacy(): string
    {
        return $this->cloze_text_legacy;
    }

    public function getLabel(Language $lng): string
    {
        return $lng->txt('assClozeTest');
    }

    public function getPersistence(): Persistence
    {
        return $this->persistence;
    }

    public function hasCapability(string $capability_class_name): bool
    {
        return array_key_exists($capability_class_name, $this->available_capabilities);
    }

    public function getCapability(string $capability_class_name): ?Capability
    {
        return $this->available_capabilities[$capability_class_name]?->withAnswerForm($this);
    }

    public function getEditView(): Edit
    {
        return $this->edit_view->withAnswerForm($this);
    }

    public function getParticipantView(): Participant
    {
        return $this->participant_view->withAnswerForm($this);
    }
}
