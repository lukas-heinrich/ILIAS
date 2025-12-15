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

namespace ILIAS\Questions\Question\Views;

use ILIAS\Questions\Presentation\Definitions\EditForm;
use ILIAS\Questions\Presentation\Definitions\EditFormFactory;
use ILIAS\Questions\Question\Question;
use ILIAS\Questions\Question\QuestionImplementation;
use ILIAS\Questions\Question\Definitions\Lifecycle;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Language\Language;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use ILIAS\UI\Component\Panel\Standard as StandardPanel;
use ILIAS\UI\Component\Input\Field\Section;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Refinery\Transformation;
use Psr\Http\Message\RequestInterface;

class Edit
{
    private const string CMD_SAVE_QUESTION = 'sq';

    public function __construct(
        private readonly Language $lng,
        private readonly \ilObjUser $current_user,
        private readonly UIFactory $ui_factory,
        private readonly Refinery $refinery,
        private readonly RequestInterface $request,
        private readonly \ilCtrl $ctrl,
        private readonly DataFactory $data_factory,
        private readonly QuestionImplementation $question
    ) {

    }

    public function create(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step,
        EditFormFactory $edit_form_factory
    ): EditForm|Question {
        return match ($step) {
            self::CMD_SAVE_QUESTION => $this->processBasicPropertiesForm(
                $url_builder,
                $step_token,
                $edit_form_factory
            ),
            default => $this->buildBasicPropertiesForm(
                $url_builder,
                $step_token,
                $edit_form_factory
            )
        };
    }

    public function edit(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        URLBuilderToken $page_id_token,
        string $step,
        EditFormFactory $edit_form_factory
    ): EditForm|Question {
        return match ($step) {
            self::CMD_SAVE_QUESTION => $this->processBasicPropertiesForm(
                $url_builder,
                $step_token,
                $edit_form_factory
            ),
            default => $this->buildBasicPropertiesForm(
                $url_builder,
                $step_token,
                $edit_form_factory
            )->withContentAfterForm(
                $this->buildPreviewPanel($url_builder, $page_id_token)
            )
        };
    }

    private function buildBasicPropertiesForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory
    ): EditForm {
        return $edit_form_factory->getEditForm(
            $url_builder->withParameter($step_token, self::CMD_SAVE_QUESTION)->buildURI(),
            $this->buildBasicPropertiesInputs(),
            true
        );
    }

    private function processBasicPropertiesForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory
    ): EditForm|Question {
        $form = $this->buildBasicPropertiesForm(
            $url_builder,
            $step_token,
            $edit_form_factory
        )->withRequest($this->request);

        $data = $form->getData();
        return $data === null
            ? $form
            : $data;
    }

    private function buildBasicPropertiesInputs(): Section
    {
        $ff = $this->ui_factory->input()->field();
        $section = $ff->section(
            [
                'title' => $ff->text($this->lng->txt('title'))
                    ->withRequired(true),
                'author' => $ff->text($this->lng->txt('author'))
                    ->withValue($this->current_user->getFullname()),
                'lifecycle' => $ff->select(
                    $this->lng->txt('qst_lifecycle'),
                    array_reduce(
                        Lifecycle::cases(),
                        function (array $c, Lifecycle $v): array {
                            $c[$v->value] = $this->lng->txt("qst_lifecycle_{$v->value}");
                            return $c;
                        },
                        []
                    )
                )->withRequired(true),
                'remarks' => $ff->textarea($this->lng->txt('qst_remarks'))
            ],
            $this->lng->txt('edit_basic_form_properties')
        )->withAdditionalTransformation($this->buildAddBasicPropertiesToQuestionTrafo());

        return $section->withValue([
            'title' => $this->question->getTitle(),
            'author' => $this->question->getAuthor(),
            'lifecycle' => $this->question->getLifecycle()->value,
            'remarks' => $this->question->getRemarks()
        ]);
    }

    private function buildAddBasicPropertiesToQuestionTrafo(): Transformation
    {
        return $this->refinery->custom()->transformation(
            function (array $vs): QuestionImplementation {
                $question = $this->question
                    ->withTitle($vs['title'])
                    ->withAuthor($vs['author'])
                    ->withRemarks($vs['remarks']);

                $lifecycle = Lifecycle::tryFrom($vs['lifecycle']);
                if ($lifecycle !== null) {
                    return $question->withLifecycle($lifecycle);
                }

                return $question;
            }
        );
    }

    private function buildPreviewPanel(
        URLBuilder $url_builder,
        URLBuilderToken $page_id_token
    ): StandardPanel {
        return $this->ui_factory->panel()->standard(
            $this->lng->txt('preview'),
            $this->ui_factory->legacy()->content($this->question->getTitle())
        )->withActions(
            $this->ui_factory->dropdown()->standard([
                $this->ui_factory->link()->standard(
                    $this->lng->txt('edit'),
                    $url_builder
                        ->withURI(
                            $this->data_factory->uri(
                                ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTargetByClass(\QstsQuestionPageGUI::class, 'edit')
                            )
                        )->withParameter($page_id_token, (string) $this->question->getPageId())
                        ->buildURI()->__toString()
                )
            ])
        );
    }
}
