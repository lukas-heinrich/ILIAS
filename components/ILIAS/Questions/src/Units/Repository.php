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

class Repository
{
    private const string UNIT_TABLE = 'il_qpl_qst_fq_unit';
    private const string CATEGORY_TABLE = 'il_qpl_qst_fq_ucat';
    private const string VARIABLES_TABLE = 'il_qpl_qst_fq_var';
    private const string RESULTS_TABLE = 'il_qpl_qst_fq_res';
    private const string RESULT_UNITS_TABLE = 'il_qpl_qst_fq_res_unit';

    /** @var list<\ILIAS\Questions\Units\Unit> $units */
    private array $units = [];
    /** @var list<\ILIAS\Questions\Units\Unit|\ILIAS\Questions\Units\Category> $categorized_units */
    private array $categorized_units = [];

    public function __construct(
        private readonly Language $lng,
        private readonly \ilDBInterface $db
    ) {
    }

    public function isCRUDAllowed(
        int $question_id,
        int $category_id
    ): bool {
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::CATEGORY_TABLE . ' WHERE category_id = %s',
            [\ilDBConstants::T_INTEGER],
            [$category_id]
        );
        $row = $this->db->fetchAssoc($res);
        return isset($row['question_fi']) && (int) $row['question_fi'] === $question_id;
    }

    public function copyCategory(
        int $question_fi,
        int $category_id,
        ?string $category_name = null
    ): int {
        $res = $this->db->queryF(
            'SELECT category FROM ' . self::CATEGORY_TABLE . ' WHERE category_id = %s',
            [\ilDBConstants::T_INTEGER],
            [$category_id]
        );
        $row = $this->db->fetchAssoc($res);

        if (null === $category_name) {
            $category_name = $row['category'];
        }

        $next_id = $this->db->nextId(self::CATEGORY_TABLE);
        $this->db->insert(
            self::CATEGORY_TABLE,
            [
                'category_id' => [\ilDBConstants::T_INTEGER, $next_id],
                'category' => [\ilDBConstants::T_TEXT, $category_name],
                'question_fi' => [\ilDBConstants::T_INTEGER, (int) $question_fi]
            ]
        );

        return $next_id;
    }

    public function copyUnitsByCategories(
        int $question_id,
        int $from_category_id,
        int $to_category_id,
    ): void {
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::UNIT_TABLE . ' WHERE category_fi = %s',
            [\ilDBConstants::T_INTEGER],
            [$from_category_id]
        );
        $i = 0;
        $units = [];
        while (($row = $this->db->fetchAssoc($res)) !== null) {
            $next_id = $this->db->nextId(self::UNIT_TABLE);

            $units[$i]['old_unit_id'] = $row['unit_id'];
            $units[$i]['new_unit_id'] = $next_id;

            $this->db->insert(
                self::UNIT_TABLE,
                [
                    'unit_id' => [\ilDBConstants::T_INTEGER, $next_id],
                    'unit' => [\ilDBConstants::T_TEXT, $row['unit']],
                    'factor' => [\ilDBConstants::T_FLOAT, $row['factor']],
                    'baseunit_fi' => [\ilDBConstants::T_INTEGER, (int) $row['baseunit_fi']],
                    'category_fi' => [\ilDBConstants::T_INTEGER, (int) $to_category_id],
                    'sequence' => [\ilDBConstants::T_INTEGER, (int) $row['sequence']],
                    'question_fi' => [\ilDBConstants::T_INTEGER, (int) $question_id]
                ]
            );
            $i++;
        }

        foreach ($units as $unit) {
            //update unit : baseunit_fi
            $this->db->update(
                self::UNIT_TABLE,
                [
                    'baseunit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                ],
                [
                    'baseunit_fi' => [\ilDBConstants::T_INTEGER, $unit['old_unit_id']],
                    'category_fi' => [\ilDBConstants::T_INTEGER, $to_category_id]
                ]
            );

            //update var : unit_fi
            $this->db->update(
                self::VARIABLES_TABLE,
                [
                    'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                ],
                [
                    'unit_fi' => [\ilDBConstants::T_INTEGER, $unit['old_unit_id']],
                    'question_fi' => [\ilDBConstants::T_INTEGER, $question_id]
                ]
            );

            //update res : unit_fi
            $this->db->update(
                self::RESULTS_TABLE,
                [
                    'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                ],
                [
                    'unit_fi' => [\ilDBConstants::T_INTEGER, $unit['old_unit_id']],
                    'question_fi' => [\ilDBConstants::T_INTEGER, $question_id]
                ]
            );

            //update res_unit : unit_fi
            $this->db->update(
                self::RESULT_UNITS_TABLE,
                [
                    'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                ],
                [
                    'unit_fi' => [\ilDBConstants::T_INTEGER, $unit['old_unit_id']],
                    'question_fi' => [\ilDBConstants::T_INTEGER, $question_id]
                ]
            );
        }
    }

    public function getCategoryUnitCount(
        int $id
    ): int {
        $row = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(category_id) FROM ' . self::UNIT_TABLE . ' WHERE category_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$id]
            )
        );

        return $row->cnt;
    }

    public function isUnitInUse(
        int $id
    ): bool {
        $use_in_result_units = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(result_unit_id) cnt FROM ' . self::RESULT_UNITS_TABLE . ' WHERE unit_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$id]
            )
        );

        if ($use_in_result_units->cnt > 0) {
            return true;
        }

        $use_in_vars = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(variable_id) cnt FROM ' . self::VARIABLES_TABLE . ' WHERE unit_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$id]
            )
        );
        if ($use_in_vars->cnt > 0) {
            return true;
        }

        $use_in_results = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(result_id) cnt FROM ' . self::RESULTS_TABLE . ' WHERE unit_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$id]
            )
        );
        if ($use_in_results->cnt > 0) {
            return true;
        }

        return false;
    }

    public function checkDeleteCategory(
        int $id
    ): ?string {
        $res = $this->db->queryF(
            'SELECT unit_id FROM ' . self::UNIT_TABLE . ' WHERE category_fi = %s',
            [\ilDBConstants::T_INTEGER],
            [$id]
        );

        while ($row = $this->db->fetchAssoc($res)) {
            $unit_res = $this->checkDeleteUnit((int) $row['unit_id'], $id);
            if ($unit_res !== null) {
                return $unit_res;
            }
        }

        return null;
    }

    public function deleteUnit(
        int $id
    ): ?string {
        $res = $this->checkDeleteUnit($id);
        if ($res !== null) {
            return $res;
        }

        $affected_rows = $this->db->manipulateF(
            'DELETE FROM ' . self::UNIT_TABLE . ' WHERE unit_id = %s',
            [\ilDBConstants::T_INTEGER],
            [$id]
        );

        if ($affected_rows > 0) {
            $this->clearUnits();
        }

        return null;
    }

    protected function loadUnits(): void
    {
        $result = $this->db->query(
            'SELECT units.*, ' . self::CATEGORY_TABLE . '.category, baseunits.unit baseunit_title' . PHP_EOL
            . 'FROM ' . self::UNIT_TABLE . ' units' . PHP_EOL
            . 'INNER JOIN ' . self::CATEGORY_TABLE . ' ON ' . self::CATEGORY_TABLE . '.category_id = units.category_fi' . PHP_EOL
            . 'LEFT JOIN ' . self::UNIT_TABLE . ' baseunits ON baseunits.unit_id = units.baseunit_fi' . PHP_EOL
            . 'ORDER BY ' . self::CATEGORY_TABLE . '.category, units.sequence'
        );

        while ($row = $this->db->fetchAssoc($result)) {
            $unit = new Unit();
            $unit->initFormArray($row);
            $this->addUnit($unit);
        }
    }

    /**
     * @return list<\ILIAS\Questions\Units\Unit|\ILIAS\Questions\Units\Category>
     */
    public function getCategorizedUnits(
        int $question_id
    ): array {
        if (count($this->categorized_units) === 0) {
            $result = $this->db->queryF(
                'SELECT	units.*, ' . self::CATEGORY_TABLE . '.category, ' . self::CATEGORY_TABLE . '.question_fi, baseunits.unit baseunit_title' . PHP_EOL
                . 'FROM	' . self::UNIT_TABLE . ' units' . PHP_EOL
                . 'INNER JOIN ' . self::CATEGORY_TABLE . ' ON ' . self::CATEGORY_TABLE . '.category_id = units.category_fi' . PHP_EOL
                . 'LEFT JOIN ' . self::UNIT_TABLE . ' baseunits ON baseunits.unit_id = units.baseunit_fi' . PHP_EOL
                . 'WHERE	units.question_fi = %s' . PHP_EOL
                . 'ORDER BY ' . self::CATEGORY_TABLE . '.category, units.sequence' . PHP_EOL,
                [\ilDBConstants::T_INTEGER],
                [$question_id]
            );

            $category = 0;
            while (($row = $this->db->fetchAssoc($result)) !== null) {
                $unit = new Unit();
                $unit->initFormArray($row);

                if ($category !== $unit->getCategory()) {
                    $cat = new Category();
                    $cat->initFormArray([
                        'category_id' => (int) $row['category_fi'],
                        'category' => $row['category'],
                        'question_fi' => (int) $row['question_fi'],
                    ]);
                    $this->categorized_units[] = $cat;
                    $category = $unit->getCategory();
                }

                $this->categorized_units[] = $unit;
            }
        }

        return $this->categorized_units;
    }

    protected function clearUnits(): void
    {
        $this->units = [];
    }

    protected function addUnit(
        Unit $unit
    ): void {
        $this->units[$unit->getId()] = $unit;
    }

    /**
     * @return list<\ILIAS\Questions\Units\Unit>
     */
    public function getUnits(): array
    {
        if ($this->units === []) {
            $this->loadUnits();
        }
        return $this->units;
    }

    /**
     * @return list<\ILIAS\Questions\Units\Unit>
     */
    public function loadUnitsForCategory(
        int $category
    ): array {
        $units = [];
        $result = $this->db->queryF(
            'SELECT units.*, baseunits.unit baseunit_title, ' . self::CATEGORY_TABLE . '.category' . PHP_EOL
            . 'FROM ' . self::UNIT_TABLE . ' units' . PHP_EOL
            . 'INNER JOIN ' . self::CATEGORY_TABLE . ' ON ' . self::CATEGORY_TABLE . '.category_id = units.category_fi' . PHP_EOL
            . 'LEFT JOIN ' . self::UNIT_TABLE . ' baseunits ON baseunits.unit_id = units.baseunit_fi' . PHP_EOL
            . 'WHERE ' . self::CATEGORY_TABLE . '.category_id = %s' . PHP_EOL
            . 'ORDER BY units.sequence',
            [\ilDBConstants::T_INTEGER],
            [$category]
        );

        while (($row = $this->db->fetchAssoc($result)) !== null) {
            $unit = new Unit();
            $unit->initFormArray($row);
            $units[] = $unit;
        }

        return $units;
    }

    public function getUnit(
        int $id
    ): ?Unit {
        if ($this->units === []) {
            $this->loadUnits();
        }

        if (array_key_exists($id, $this->units)) {
            return $this->units[$id];
        }

        // Maybe this is a new unit, reload $this->units
        $this->loadUnits();

        return $this->units[$id] ?? null;
    }

    /**
     * @return array<int, array{value: int, text: string, qst_id: int}>
     */
    public function getUnitCategories(): array
    {
        $categories = [];
        $result = $this->db->queryF(
            'SELECT * FROM ' . self::CATEGORY_TABLE . ' WHERE question_fi > %s ORDER BY category',
            [\ilDBConstants::T_INTEGER],
            [0]
        );

        while (($row = $this->db->fetchAssoc($result)) !== null) {
            $value = $this->lng->txt($row['category']) === "-qpl_qst_formulaquestion_{$row['category']}-"
                ? $row['category']
                : $this->lng->txt($row['category']);

            if (trim($row['category']) !== '') {
                $cat = [
                    'value' => (int) $row['category_id'],
                    'text' => $value,
                    'qst_id' => (int) $row['question_fi']
                ];
                $categories[(int) $row['category_id']] = $cat;
            }
        }

        return $categories;
    }

    /**
     * @return array<int, array{value: int, text: string, qst_id: int}>
     */
    public function getAdminUnitCategories(): array
    {
        $categories = [];

        $result = $this->db->queryF(
            'SELECT * FROM ' . self::CATEGORY_TABLE . ' WHERE question_fi = %s  ORDER BY category',
            [\ilDBConstants::T_INTEGER],
            [0]
        );

        while (($row = $this->db->fetchAssoc($result)) !== null) {
            $value = $this->lng->txt($row['category']) === "-qpl_qst_formulaquestion_{$row['category']}-'"
                ? $row['category']
                : $this->lng->txt($row['category']);

            if (trim($row['category']) !== '') {
                $cat = [
                    'value' => (int) $row['category_id'],
                    'text' => $value,
                    'qst_id' => (int) $row['question_fi']
                ];
                $categories[(int) $row['category_id']] = $cat;
            }
        }

        return $categories;
    }

    public function saveUnitOrder(
        int $question_id,
        int $unit_id,
        int $sequence
    ): void {
        $this->db->manipulateF(
            'UPDATE ' . self::UNIT_TABLE . ' SET sequence = %s WHERE unit_id = %s AND question_fi = %s',
            [
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER
            ],
            [
                $sequence,
                $unit_id,
                $question_id
            ]
        );
    }

    public function checkDeleteUnit(
        int $id,
        ?int $category_id = null
    ): ?string {
        $use_in_vars = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(variable_id) cnt FROM ' . self::VARIABLES_TABLE . ' WHERE unit_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$id]
            )
        );
        if ($use_in_vars->cnt > 0) {
            return $this->lng->txt('err_unit_in_variables');
        }

        $use_in_results = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(result_id) cnt FROM ' . self::RESULTS_TABLE . ' WHERE unit_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$id]
            )
        );
        if ($use_in_results->cnt > 0) {
            return $this->lng->txt('err_unit_in_results');
        }

        $additional_where = 'unit_id != %s';
        $values_array = [$id, $id];
        if ($category_id !== null) {
            $additional_where = 'category_fi != %s';
            $values_array = [$id, $category_id];
        }

        $use_as_base_unit = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(unit_id) cnt FROM ' . self::UNIT_TABLE . '' . PHP_EOL
                    . "WHERE baseunit_fi = %s AND {$additional_where}",
                [\ilDBConstants::T_INTEGER, \ilDBConstants::T_INTEGER],
                $values_array
            )
        );

        if ($use_as_base_unit->cnt > 0) {
            return $this->lng->txt('err_unit_is_baseunit');
        }

        return null;
    }

    public function getUnitCategoryById(
        int $id
    ): Category {
        $res = $this->db->query(
            'SELECT * FROM ' . self::CATEGORY_TABLE . ' WHERE category_id = '
                . $this->db->quote($id, \ilDBConstants::T_INTEGER)
        );

        if ($this->db->numRows($res) === 0) {
            throw new \ilException('un_category_not_exist');
        }

        $category = new Category();
        $category->initFormArray($this->db->fetchAssoc($res));
        return $category;
    }

    public function saveCategory(
        Category $category
    ): void {
        $row = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(category_id) cnt FROM ' . self::CATEGORY_TABLE . '' . PHP_EOL
                . 'WHERE category = %s AND question_fi = %s AND category_id != %s',
                [
                    \ilDBConstants::T_TEXT,
                    \ilDBConstants::T_INTEGER,
                    \ilDBConstants::T_INTEGER
                ],
                [
                    $category->getCategory(),
                    $category->getQuestionFi(),
                    $category->getId()
                ]
            )
        );
        if ($row->cnt > 0) {
            throw new \ilException('err_wrong_categoryname');
        }

        $this->db->manipulateF(
            'UPDATE ' . self::CATEGORY_TABLE . ' SET category = %s WHERE question_fi = %s AND category_id = %s',
            [
                \ilDBConstants::T_TEXT,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER
            ],
            [
                $category->getCategory(),
                $category->getQuestionFi(),
                $category->getId()
            ]
        );
    }

    public function saveNewUnitCategory(
        Category $category
    ): void {
        $row = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(category_id) cnt FROM ' . self::CATEGORY_TABLE . ' WHERE category = %s AND question_fi = %s',
                [
                    \ilDBConstants::T_TEXT,
                    \ilDBConstants::T_INTEGER
                ],
                [
                    $category->getCategory(),
                    $category->getQuestionFi()
                ]
            )
        );
        if ($row->cnt > 0) {
            throw new \ilException('err_wrong_categoryname');
        }

        $next_id = $this->db->nextId(self::CATEGORY_TABLE);
        $this->db->manipulateF(
            'INSERT INTO ' . self::CATEGORY_TABLE . ' (category_id, category, question_fi) VALUES (%s, %s, %s)',
            [
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_TEXT,
                \ilDBConstants::T_INTEGER
            ],
            [
                $next_id,
                $category->getCategory(),
                $category->getQuestionFi()
            ]
        );
        $category->setId($next_id);
    }

    /**
     * @return list<\ILIAS\Questions\Units\Category>
     */
    public function getAllUnitCategories(
        int $question_id
    ): array {
        $categories = [];
        $result = $this->db->queryF(
            'SELECT * FROM ' . self::CATEGORY_TABLE . ' WHERE question_fi = %s OR question_fi = %s ORDER BY category',
            [
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER
            ],
            [
                $question_id,
                0
            ]
        );

        while ($row = $this->db->fetchAssoc($result)) {
            $category = new Category();
            $category->initFormArray($row);
            $categories[] = $category;
        }

        return $categories;
    }

    public function deleteCategory(
        int $id
    ): ?string {
        if ($this->checkDeleteCategory($id) !== null) {
            return $this->lng->txt('err_category_in_use');
        }

        $res = $this->db->queryF(
            'SELECT * FROM ' . self::UNIT_TABLE . ' WHERE category_fi = %s',
            [\ilDBConstants::T_INTEGER],
            [$id]
        );
        while (($row = $this->db->fetchAssoc($res)) !== null) {
            $this->deleteUnit((int) $row['unit_id']);
        }

        $ar = $this->db->manipulateF(
            'DELETE FROM ' . self::CATEGORY_TABLE . ' WHERE category_id = %s',
            [\ilDBConstants::T_INTEGER],
            [$id]
        );

        if ($ar > 0) {
            $this->clearUnits();
        }

        return null;
    }

    public function createNewUnit(
        int $question_id,
        Unit $unit
    ): void {
        $next_id = $this->db->nextId(self::UNIT_TABLE);
        $this->db->manipulateF(
            'INSERT INTO ' . self::UNIT_TABLE . ' (unit_id, unit, factor, baseunit_fi, category_fi, sequence, question_fi)' . PHP_EOL
            . 'VALUES (%s, %s, %s, %s, %s, %s, %s)',
            [
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_TEXT,
                \ilDBConstants::T_FLOAT,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER
            ],
            [
                $next_id,
                $unit->getUnit(),
                1,
                0,
                $unit->getCategory(),
                0,
                $question_id
            ]
        );
        $unit->setId($next_id);
        $unit->setFactor(1.0);
        $unit->setBaseUnit(0);
        $unit->setSequence(0);

        $this->clearUnits();
    }

    public function saveUnit(
        int $question_id,
        Unit $unit
    ): void {
        $row = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT COUNT(unit_id) cnt FROM ' . self::UNIT_TABLE . ' WHERE unit_id = %s',
                [\ilDBConstants::T_INTEGER],
                [$unit->getId()]
            )
        );
        if ($row->cnt === 0) {
            return;
        }

        if ($unit->getBaseUnit() === 0 || $unit->getBaseUnit() === $unit->getId()) {
            $unit->setFactor(1);
        }

        $ar = $this->db->manipulateF(
            'UPDATE ' . self::UNIT_TABLE . '' . PHP_EOL
            . 'SET unit = %s, factor = %s, baseunit_fi = %s, category_fi = %s, sequence = %s' . PHP_EOL
            . 'WHERE unit_id = %s AND question_fi = %s',
            [
                \ilDBConstants::T_TEXT,
                \ilDBConstants::T_FLOAT,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER,
                \ilDBConstants::T_INTEGER
            ],
            [
                $unit->getUnit(), $unit->getFactor(), (int) $unit->getBaseUnit(),
                $unit->getCategory(),
                $unit->getSequence(),
                $unit->getId(),
                $question_id
            ]
        );
        if ($ar > 0) {
            $this->clearUnits();
        }
    }

    public function cloneUnits(
        int $from_consumer_id,
        int $to_consumer_id
    ): void {
        $category_mapping = [];

        $res = $this->db->queryF(
            'SELECT * FROM ' . self::CATEGORY_TABLE . ' WHERE question_fi = %s',
            [\ilDBConstants::T_INTEGER],
            [$from_consumer_id]
        );
        while ($row = $this->db->fetchAssoc($res)) {
            $new_category_id = $this->copyCategory((int) $row['category_id'], $to_consumer_id);
            $category_mapping[$row['category_id']] = $new_category_id;
        }

        foreach ($category_mapping as $old_category_id => $new_category_id) {
            $res = $this->db->queryF(
                'SELECT * FROM ' . self::UNIT_TABLE . ' WHERE category_fi = %s',
                [\ilDBConstants::T_INTEGER],
                [$old_category_id]
            );

            $i = 0;
            $units = [];
            while ($row = $this->db->fetchAssoc($res)) {
                $next_id = $this->db->nextId(self::UNIT_TABLE);

                $units[$i]['old_unit_id'] = $row['unit_id'];
                $units[$i]['new_unit_id'] = $next_id;

                $this->db->insert(
                    self::UNIT_TABLE,
                    [
                        'unit_id' => [\ilDBConstants::T_INTEGER, $next_id],
                        'unit' => [\ilDBConstants::T_TEXT, $row['unit']],
                        'factor' => [\ilDBConstants::T_FLOAT, $row['factor']],
                        'baseunit_fi' => [\ilDBConstants::T_INTEGER, (int) $row['baseunit_fi']],
                        'category_fi' => [\ilDBConstants::T_INTEGER, (int) $new_category_id],
                        'sequence' => [\ilDBConstants::T_INTEGER, (int) $row['sequence']],
                        'question_fi' => [\ilDBConstants::T_INTEGER, $to_consumer_id]
                    ]
                );
                $i++;
            }

            foreach ($units as $unit) {
                //update unit : baseunit_fi
                $this->db->update(
                    self::UNIT_TABLE,
                    [
                        'baseunit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                    ],
                    [
                        'baseunit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['old_unit_id']],
                        'question_fi' => [\ilDBConstants::T_INTEGER, $to_consumer_id]
                    ]
                );

                //update var : unit_fi
                $this->db->update(
                    self::VARIABLES_TABLE,
                    [
                        'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                    ],
                    [
                        'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['old_unit_id']],
                        'question_fi' => [\ilDBConstants::T_INTEGER, $to_consumer_id]
                    ]
                );

                //update res : unit_fi
                $this->db->update(
                    self::RESULTS_TABLE,
                    [
                        'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                    ],
                    [
                        'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['old_unit_id']],
                        'question_fi' => [\ilDBConstants::T_INTEGER, $to_consumer_id]
                    ]
                );

                //update res_unit : unit_fi
                $this->db->update(
                    self::RESULT_UNITS_TABLE,
                    [
                        'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['new_unit_id']]
                    ],
                    [
                        'unit_fi' => [\ilDBConstants::T_INTEGER, (int) $unit['old_unit_id']],
                        'question_fi' => [\ilDBConstants::T_INTEGER, $to_consumer_id]
                    ]
                );
            }
        }
    }


    public function lookupUnitFactor(
        int $a_unit_id
    ): float {
        $res = $this->db->fetchObject(
            $this->db->queryF(
                'SELECT factor FROM il_qpl_qst_fq_unit WHERE unit_id = %s',
                [\ilDBConstants::T_INTEGER],
                [$a_unit_id]
            )
        );

        return (float) $row->factor;
    }
}
