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

use ILIAS\Questions\AnswerForm\Properties as PropertiesInterface;
use ILIAS\Questions\AnswerForm\Views\Edit as EditViewInterface;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\AnswerForm\Factory as PropertiesFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\AnswerForm\Properties;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText\Factory as ClozeTextFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Factory as GapFactory;
use ILIAS\Questions\Presentation\Definitions\EditForm;
use ILIAS\Questions\Presentation\Definitions\EditFormFactory;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Language\Language;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Panel\Standard as StandardPanel;
use ILIAS\Refinery\Factory as Refinery;

class Edit implements EditViewInterface
{
    private const string STEP_SET_GAP_TYPES = 'sgt';
    private const string STEP_SET_ANSWER_OPTIONS = 'sao';
    private const string STEP_SET_POINTS = 'sap';
    private const string STEP_SAVE = 's';

    public function __construct(
        private readonly Language $lng,
        private readonly UIFactory $ui_factory,
        private readonly Refinery $refinery,
        private readonly HTTPServices $http,
        private readonly PropertiesFactory $properties_factory,
        private readonly ClozeTextFactory $cloze_text_factory,
        private readonly GapFactory $gap_factory
    ) {
    }

    public function create(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step,
        EditFormFactory $edit_form_factory,
        PropertiesInterface $properties
    ): EditForm|Properties {
        return match($step) {
            self::STEP_SET_GAP_TYPES => $this->processBasicEditingForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties->withValuesFromCarry(
                    $this->refinery,
                    $this->cloze_text_factory,
                    $this->gap_factory,
                    $edit_form_factory->getCarrySectionData(
                        $this->http->wrapper()->post(),
                        $this->refinery
                    )
                )
            ),
            self::STEP_SET_ANSWER_OPTIONS => $this->processGapTypesForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties->withValuesFromCarry(
                    $this->refinery,
                    $this->cloze_text_factory,
                    $this->gap_factory,
                    $edit_form_factory->getCarrySectionData(
                        $this->http->wrapper()->post(),
                        $this->refinery
                    )
                )
            ),
            self::STEP_SET_POINTS => $this->processAnswerOptionsForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties->withValuesFromCarry(
                    $this->refinery,
                    $this->cloze_text_factory,
                    $this->gap_factory,
                    $edit_form_factory->getCarrySectionData(
                        $this->http->wrapper()->post(),
                        $this->refinery
                    )
                )
            ),
            self::STEP_SAVE => $this->processAssignPointsForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties->withValuesFromCarry(
                    $this->refinery,
                    $this->cloze_text_factory,
                    $this->gap_factory,
                    $edit_form_factory->getCarrySectionData(
                        $this->http->wrapper()->post(),
                        $this->refinery
                    )
                )
            ),
            default => $this->buildBasicEditingForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties
            )
        };
    }

    public function edit(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step,
        EditFormFactory $edit_form_factory,
        PropertiesInterface $properties,
    ): EditForm|Properties {

    }

    public function other(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        string $step,
        EditFormFactory $edit_form_factory,
        PropertiesInterface $properties
    ): EditForm|Properties {

    }

    private function buildBasicEditingForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm {
        return $edit_form_factory->getEditForm(
            $url_builder->withParameter($step_token, self::STEP_SET_GAP_TYPES)->buildURI(),
            $properties->buildBasicEditingInputs(
                $this->lng,
                $this->ui_factory->input()->field(),
                $this->refinery,
                $this->properties_factory,
                $this->cloze_text_factory
            ),
            false
        );
    }

    private function processBasicEditingForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm {
        $form = $this->buildBasicEditingForm(
            $url_builder,
            $step_token,
            $edit_form_factory,
            $properties
        )->withRequest($this->http->request());

        $data = $form->getData();
        return $data === null
            ? $form
            : $this->buildGapTypesForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $data
            );
    }

    private function buildGapTypesForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm {
        $ff = $this->ui_factory->input()->field();
        return $edit_form_factory->getEditForm(
            $url_builder->withParameter($step_token, self::STEP_SET_ANSWER_OPTIONS)->buildURI(),
            $properties->getGaps()->buildGapsTypeInputs(
                $this->lng,
                $ff,
                $this->refinery,
                $this->gap_factory->getAvailableGapTypesOptionsArray($this->lng)
            ),
            false,
            $properties->withClozeText($properties->getClozeText())
                ->buildCarryInputs($ff)
        )->withContentBeforeForm(
            $this->buildClozeTextPanel($properties)
        );
    }

    private function processGapTypesForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties,
    ): EditForm {
        $form = $this->buildGapTypesForm(
            $url_builder,
            $step_token,
            $edit_form_factory,
            $properties
        )->withRequest($this->http->request());

        $data = $form->getData();
        return $data === null
            ? $form
            : $this->buildAnswerOptionsForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties->withGaps($data)
            );
    }

    private function buildAnswerOptionsForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm {
        $ff = $this->ui_factory->input()->field();
        return $edit_form_factory->getEditForm(
            $url_builder->withParameter($step_token, self::STEP_SET_POINTS)->buildURI(),
            $properties->getGaps()->buildAnswerOptionsInputs($this->lng, $ff, $this->refinery),
            false,
            $properties->buildCarryInputs($ff)
        )->withContentBeforeForm(
            $this->buildClozeTextPanel($properties)
        );
    }

    private function processAnswerOptionsForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm {
        $form = $this->buildAnswerOptionsForm(
            $url_builder,
            $step_token,
            $edit_form_factory,
            $properties
        )->withRequest($this->http->request());

        $data = $form->getData();
        return $data === null
            ? $form
            : $this->buildAssignPointsForm(
                $url_builder,
                $step_token,
                $edit_form_factory,
                $properties->withGaps($data)
            );
    }

    private function buildAssignPointsForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm {
        $ff = $this->ui_factory->input()->field();
        return $edit_form_factory->getEditForm(
            $url_builder->withParameter($step_token, self::STEP_SAVE)->buildURI(),
            $properties->getGaps()->buildPointInputs($this->lng, $ff, $this->refinery),
            true,
            $properties->buildCarryInputs($ff)
        )->withContentBeforeForm(
            $this->buildClozeTextPanel($properties)
        );
    }

    private function processAssignPointsForm(
        URLBuilder $url_builder,
        URLBuilderToken $step_token,
        EditFormFactory $edit_form_factory,
        Properties $properties
    ): EditForm|Properties {
        $form = $this->buildAssignPointsForm(
            $url_builder,
            $step_token,
            $edit_form_factory,
            $properties
        )->withRequest($this->http->request());

        $data = $form->getData();
        return $data === null
            ? $form->withContentBeforeForm(
                $this->buildClozeTextPanel($properties)
            ) : $properties->withGaps($data);
    }

    private function buildClozeTextPanel(
        Properties $properties
    ): StandardPanel {
        return $this->ui_factory->panel()->standard(
            $this->lng->txt('cloze_text'),
            $this->ui_factory->legacy()->content(
                $properties->getClozeText()->getRenderedMarkdown(
                    $properties->getGaps()
                )
            )
        );
    }
}
