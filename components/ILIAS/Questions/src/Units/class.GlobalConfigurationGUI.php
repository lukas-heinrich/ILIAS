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

class GlobalConfigurationGUI extends ConfigurationGUI
{
    public const REQUEST_PARAM_SUB_CONTEXT = 'context';

    protected function getDefaultCommand(): string
    {
        return 'showGlobalUnitCategories';
    }

    public function getUnitCategoryOverviewCommand(): string
    {
        return 'showGlobalUnitCategories';
    }

    public function isCRUDContext(): bool
    {
        return true;
    }

    public function getUniqueId(): string
    {
        return $this->request->getQuestionId() . '_global';
    }

    #[\Override]
    protected function showGlobalUnitCategories(): void
    {
        if ($this->rbac_system->checkAccess('write', $this->request->getRefId())) {
            $this->toolbar->addButton(
                $this->lng->txt('un_add_category'),
                $this->ctrl->getLinkTargetByClass(
                    self::class,
                    'showUnitCategoryCreationForm'
                )
            );
        }

        parent::showGlobalUnitCategories();
    }

    #[\Override]
    protected function showUnitCategories(array $categories): void
    {
        $table = new \ilGlobalUnitCategoryTableGUI($this, $this->getUnitCategoryOverviewCommand());
        $table->setData($categories);

        $this->tpl->setContent($table->getHTML());
    }
}
