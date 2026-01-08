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
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Factory as PropertiesFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Properties;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText\Factory as ClozeTextFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Factory as GapFactory;
use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Gap;
use ILIAS\Questions\Presentation\Definitions\Environment;
use ILIAS\Questions\Presentation\Layout\EditForm;
use ILIAS\Questions\Presentation\Layout\EditOverview;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Language\Language;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Modal\Interruptive as InterruptiveModal;
use ILIAS\UI\Component\Modal\InterruptiveItem\Standard as InterruptiveItem;
use ILIAS\UI\Component\Panel\Standard as StandardPanel;
use ILIAS\Refinery\Factory as Refinery;

class Edit implements EditViewInterface
{
    private const string STEP_EDIT_BASIC_PROPERTIES = 'ebp';
    private const string STEP_CONFIRMED_GAP_REMOVAL = 'cgr';
    public const string STEP_SET_GAP_TYPES = 'sgt';
    public const string STEP_SET_ANSWER_OPTIONS = 'sao';
    public const string STEP_SET_POINTS = 'sp';
    private const string STEP_SAVE = 's';

    public const array PARAMETER_NAMESPACE = ['c'];
    public const string OVERVIEW_TABLE_ROW_ID = 'g';

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

    #[\Override]
    public function create(
        Environment $environment
    ): EditForm|Properties {
        return match($environment->getStep()) {
            '' => $this->buildBasicEditingForm($environment),
            default => $this->callIntermediateStep($environment)
        };
    }

    #[\Override]
    public function edit(
        Environment $environment
    ): EditOverview|EditForm|Properties {
        return match ($environment->getStep()) {
            '' => $environment->getPresentationFactory()->getEditOverview(
                $environment,
                $environment->getUrlBuilderWithStepParameter(self::STEP_EDIT_BASIC_PROPERTIES)
                    ->buildURI()
            ),
            self::STEP_EDIT_BASIC_PROPERTIES => $this->buildBasicEditingForm($environment),
            default => $this->callIntermediateStep($environment)
        };
    }

    #[\Override]
    public function other(
        Environment $environment
    ): EditForm|Properties {

    }

    private function callIntermediateStep(
        Environment $environment
    ): EditForm|Properties {
        $environment_with_properties = $environment->getAnswerFormProperties()->withValuesFromCarry(
            $this->refinery,
            $this->cloze_text_factory,
            $this->gap_factory,
            $environment->getPresentationFactory()->getCarrySectionData(
                $this->http->wrapper()->post(),
                $this->refinery
            )
        );

        return match ($environment->getStep()) {
            self::STEP_SET_GAP_TYPES,
            self::STEP_CONFIRMED_GAP_REMOVAL => $this->processBasicEditingForm(
                $environment->withAnswerFormProperties(
                    $environment_with_properties
                )
            ),
            self::STEP_SET_ANSWER_OPTIONS => $this->processGapTypesForm(
                $environment->withAnswerFormProperties(
                    $environment_with_properties
                )
            ),
            self::STEP_SET_POINTS => $this->processAnswerOptionsForm(
                $environment->withAnswerFormProperties(
                    $environment_with_properties
                )
            ),
            self::STEP_SAVE => $this->processAssignPointsForm(
                $environment->withAnswerFormProperties(
                    $environment_with_properties
                )
            )
        };
    }

    private function buildBasicEditingForm(
        Environment $environment
    ): EditForm {
        return $environment->getPresentationFactory()->getEditForm(
            $environment->getUrlBuilderWithStepParameter(self::STEP_SET_GAP_TYPES),
            $environment->getAnswerFormProperties()->buildBasicEditingInputs(
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
        Environment $environment
    ): EditForm|Properties {
        $form = $this->buildBasicEditingForm(
            $environment
        )->withRequest($this->http->request());

        $data = $form->getData();
        if ($data === null) {
            return $form;
        }

        $new_gaps = $data->getGaps();
        $old_gaps = $environment->getAnswerFormProperties()->getGaps();

        if ($environment->getStep() !== self::STEP_CONFIRMED_GAP_REMOVAL) {
            $removed_gaps = $new_gaps->getRemovedGaps($old_gaps);
            if ($removed_gaps !== []) {
                return $form->withConfirmation(
                    $this->buildRemovedGapsConfirmation(
                        $environment,
                        $removed_gaps
                    )
                );
            }
        }

        if ($new_gaps->getAddedGaps($old_gaps) === []) {
            return $data;
        }

        return $this->buildGapTypesForm(
            $environment->withAnswerFormProperties($data)
        );
    }

    private function buildGapTypesForm(
        Environment $environment
    ): EditForm {
        $properties = $environment->getAnswerFormProperties();
        $ff = $this->ui_factory->input()->field();
        return $environment->getPresentationFactory()->getEditForm(
            $environment->getUrlBuilderWithStepParameter(self::STEP_SET_ANSWER_OPTIONS),
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
        Environment $environment
    ): EditForm {
        $form = $this->buildGapTypesForm(
            $environment
        )->withRequest($this->http->request());

        $data = $form->getData();
        return $data === null
            ? $form
            : $this->buildAnswerOptionsForm(
                $environment->withAnswerFormProperties(
                    $environment->getAnswerFormProperties()->withGaps($data)
                )
            );
    }

    private function buildAnswerOptionsForm(
        Environment $environment
    ): EditForm {
        $properties = $environment->getAnswerFormProperties();
        $ff = $this->ui_factory->input()->field();
        return $environment->getPresentationFactory()->getEditForm(
            $environment->getUrlBuilderWithStepParameter(self::STEP_SET_POINTS),
            $properties->getGaps()->buildAnswerOptionsInputs($this->lng, $ff, $this->refinery),
            false,
            $properties->buildCarryInputs($ff)
        )->withContentBeforeForm(
            $this->buildClozeTextPanel($properties)
        );
    }

    private function processAnswerOptionsForm(
        Environment $environment
    ): EditForm {
        $form = $this->buildAnswerOptionsForm(
            $environment
        )->withRequest($this->http->request());

        $data = $form->getData();
        return $data === null
            ? $form
            : $this->buildAssignPointsForm(
                $environment->withAnswerFormProperties(
                    $environment->getAnswerFormProperties()->withGaps($data)
                )
            );
    }

    private function buildAssignPointsForm(
        Environment $environment
    ): EditForm {
        $properties = $environment->getAnswerFormProperties();
        $ff = $this->ui_factory->input()->field();
        return $environment->getPresentationFactory()->getEditForm(
            $environment->getUrlBuilderWithStepParameter(self::STEP_SAVE),
            $properties->getGaps()->buildPointInputs($this->lng, $ff, $this->refinery),
            true,
            $properties->buildCarryInputs($ff)
        )->withContentBeforeForm(
            $this->buildClozeTextPanel($properties)
        );
    }

    private function processAssignPointsForm(
        Environment $environment
    ): EditForm|Properties {
        $form = $this->buildAssignPointsForm(
            $environment
        )->withRequest($this->http->request());

        $properties = $environment->getAnswerFormProperties();
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
                $properties->getClozeText()->getRenderedMarkdownForEditingPresentation(
                    $properties->getGaps()
                )
            )
        );
    }

    /**
     * @param array<\ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Gap> $removed_gaps
     */
    private function buildRemovedGapsConfirmation(
        Environment $environment,
        array $removed_gaps
    ): InterruptiveModal {
        return $this->ui_factory->modal()->interruptive(
            $this->lng->txt('confirm'),
            $this->lng->txt('confirm_remove_gaps'),
            $environment->getUrlBuilderWithStepParameter(
                self::STEP_CONFIRMED_GAP_REMOVAL
            )->buildURI()->__toString()
        )->withAffectedItems(
            array_map(
                fn(Gap $v): InterruptiveItem => $this->ui_factory->modal()
                    ->interruptiveItem()->standard(
                        $v->getAnswerInputId()->toString(),
                        $v->buildShortenedGapName()
                    ),
                $removed_gaps
            )
        );
    }
}
