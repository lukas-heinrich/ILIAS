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

namespace ILIAS\Questions\AnswerFormTypes\Cloze\Views;

use ILIAS\Questions\AnswerForm\Views\Edit as EditViewInterface;
use ILIAS\Questions\AnswerFormTypes\Cloze\Definitions\ScoringIdentical;
use ILIAS\Questions\AnswerFormTypes\Cloze\Type;
use ILIAS\Questions\Question\Definitions\TextMatchingOptions;
use ILIAS\Questions\Question\Persistence\UpdateQuery;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Language\Language;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\Refinery\Factory as Refinery;
use Psr\Http\Message\RequestInterface;

class Edit implements EditViewInterface
{
    private const string SET_GAP_TYPES = 'sgt';

    private ?Type $type = null;

    public function __construct(
        private readonly Language $lng,
        private readonly UIFactory $ui_factory,
        private readonly Refinery $refinery,
        private readonly RequestInterface $request,
        private readonly DataFactory $data_factory
    ) {
    }

    public function create(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step
    ): array|UpdateQuery {
        return match($step) {
            self::SET_GAP_TYPES => [$this->processBasicEditingForm($url_builder, $step_token)],
            default => [$this->buildBasicEditingForm($url_builder, $step_token)]
        };
    }

    public function edit(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step
    ): array|UpdateQuery {

    }

    public function other(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step
    ): array|UpdateQuery {

    }

    public function withAnswerForm(Type $type): self
    {
        $clone = clone $this;
        $clone->type = $type;
        return $clone;
    }

    private function buildBasicEditingForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token
    ): StandardForm {
        $ff = $this->ui_factory->input()->field();
        return $this->ui_factory->input()->container()->form()->standard(
            $url_builder->withParameter($step_token, self::SET_GAP_TYPES)->buildURI()->__toString(),
            [
                'form' => $ff->section(
                    [
                        'cloze_text' => $ff->markdown(
                            new \ilUIMarkdownPreviewGUI(),
                            $this->lng->txt('cloze_text')
                        )->withRequired(true)
                        ->withValue($this->type->getClozeText()),
                        'matching_method' => $ff->select(
                            $this->lng->txt('text_rating'),
                            TextMatchingOptions::buildOptionsList($this->lng)
                        )->withRequired(true)
                        ->withValue($this->type->getMatchingMethod()->value),
                        'min_autocomplete' => $ff->numeric($this->lng->txt('min_auto_complete'))
                            ->withRequired(true)
                            ->withValue($this->type->getAutocompleteLength()),
                        'identical_responses' => $ff->select(
                            $this->lng->txt('scoring_of_identical_responses'),
                            ScoringIdentical::buildOptionsList($this->lng)
                        )->withRequired(true)
                        ->withValue($this->type->getScoringIdentical()->value),
                        'max_chars' => $ff->numeric($this->lng->txt('cloze_fixed_textlength')),
                        'enable_combinations' => $ff->checkbox($this->lng->txt('cloze_enable_combinations'))
                    ],
                    $this->lng->txt('create_answer_form')
                )
            ]
        )->withSubmitLabel($this->lng->txt('next'));
    }

    private function processBasicEditingForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token
    ): StandardForm {
        $form = $this->buildBasicEditingForm($url_builder, $step_token)->withRequest($this->request);
        $data = $form->getData();
        if ($data === null) {
            return $form;
        }

        $this->buildGapTypesForm($url_builder, $step_token, $data);
    }
}
