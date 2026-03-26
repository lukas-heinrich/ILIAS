<?php

use ILIAS\Questions\ExportImport\Foundation\Serializing\SimpleXMLSerializer;
use ILIAS\TestQuestionPool\ExportImport\QuestionPoolExporter;

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

/**
 * Used for container export with tests
 *
 * @author Helmut Schottmüller <ilias@aurealis.de>
 * @version $Id$
 * @ingroup components\ILIASTest
    /**
     * Initialisation
     */
    public function init(): void
    {
    }

    public function getXmlRepresentation(string $a_entity, string $a_schema_version, string $a_id): string
    {
        $qpl = new ilObjQuestionPool($a_id, false);
        $qpl->loadFromDb();

        $qpl_exp = new ilQuestionpoolExport($qpl, 'xml');
        $qpl_exp->buildExportFile();

        global $DIC; /* @var ILIAS\DI\Container $DIC */
        $DIC['ilLog']->write(__METHOD__ . ': Created zip file');
        return ''; // sagt mjansen
    }

    /**
     * Get tail dependencies
     * @param		string		entity
     * @param		string		target release
     * @param		array		ids
     * @return        array        array of array with keys "component", entity", "ids"
     */
    public function getXmlExportTailDependencies(string $a_entity, string $a_target_release, array $a_ids): array
    {
        if ($a_entity == 'qpl') {
            $deps = [];

            $taxIds = $this->getDependingTaxonomyIds($a_ids);

            if (count($taxIds)) {
                $deps[] = [
                    'component' => 'components/ILIAS/Taxonomy',
                    'entity' => 'tax',
                    'ids' => $taxIds
                ];
            }

            $md_ids = [];
            foreach ($a_ids as $id) {
                $md_ids[] = $id . ':0:qpl';
            }
            if ($md_ids !== []) {
                $deps[] = [
                    'component' => 'components/ILIAS/MetaData',
                    'entity' => 'md',
                    'ids' => $md_ids
                ];
            }

            return $deps;
        }

        return parent::getXmlExportTailDependencies($a_entity, $a_target_release, $a_ids);
    }

    /**
     * @param array $testObjIds
     * @return array $taxIds
     */
    private function getDependingTaxonomyIds($poolObjIds): array
    {
        $taxIds = [];

        foreach ($poolObjIds as $poolObjId) {
            foreach (ilObjTaxonomy::getUsageOfObject($poolObjId) as $taxId) {
                $taxIds[$taxId] = $taxId;
            }
        }

        return $taxIds;
    }

    /**
     * Returns schema versions that the component can export to.
     * ILIAS chooses the first one, that has min/max constraints which
     * fit to the target release. Please put the newest on top.
     * @return array
     */
    public function getValidSchemaVersions(string $a_entity): array
    {
        return [
            "4.1.0" => [
                "namespace" => "http://www.ilias.de/Modules/TestQuestionPool/htlm/4_1",
                "xsd_file" => "ilias_qpl_4_1.xsd",
                "uses_dataset" => false,
                "min" => "4.1.0",
                "max" => ""]
        ];
    }
}
