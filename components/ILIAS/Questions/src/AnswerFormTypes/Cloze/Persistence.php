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

use ILIAS\Questions\AnswerForm\Persistence as PersistenceInterface;
use ILIAS\Questions\Persistence\TableTypes;
use ILIAS\Questions\Persistence\Query;
use ILIAS\Questions\Persistence\Join;
use ILIAS\Questions\Persistence\Column;
use ILIAS\Questions\Persistence\JoinType;
use ILIAS\Questions\Persistence\Select;
use ILIAS\Questions\Persistence\TableNameBuilder;
use ILIAS\Questions\Persistence\TableNameSpace;
use ILIAS\Questions\Persistence\TableNameSpaceCore;

class Persistence implements PersistenceInterface
{
    private const string ID_COLUMN = 'id';

    private const string ANSWER_FORM_TABLE_ID_COLUMN = 'answer_form_id';
    private const string ANSWER_FORM_TABLE_FOREIGN_KEY_COLUMN = 'answer_form_id';
    private const array ANSWER_FORM_TABLE_COLUMNS = [
        'answer_form_id',
        'scoring_identical_responses',
        'combinations_activated'
    ];

    private const string ANSWER_INPUTS_TABLE_FOREIGN_KEY_COLUMN = 'answer_form_id';
    private const array ANSWER_INPUTS_TABLE_COLUMNS = [
        'id',
        'answer_form_id',
        'position',
        'gap_type',
        'max_chars',
        'step_size',
        'text_matching_method',
        'min_autocomplete',
        'shuffle_answer_options'
    ];

    private const string ANSWER_OPTIONS_TABLE_FOREIGN_KEY_COLUMN = 'answer_input_id';
    private const array ANSWER_OPTIONS_TABLE_COLUMNS = [
        'id',
        'answer_input_id',
        'position',
        'text_value',
        'points',
        'lower_limit',
        'upper_limit'
    ];

    private const string COMBINATIONS_TABLE_IDENTIFIER = 'combinations';
    private const string COMBINATIONS_TABLE_FOREIGN_KEY_COLUMN = 'answer_form_id';
    private const array COMBINATIONS_TABLE_COLUMNS = [
        'id',
        'answer_form_id',
        'answer_options',
        'points'
    ];

    public function __construct(
        private readonly TableNameSpaceCore $table_namespace
    ) {
    }

    public function getPublicNameSpace(): TableNameSpace
    {
        return $this->table_namespace;
    }

    public function getColumns(
        TableNameBuilder $table_name_builder,
        TableTypes $table_type,
        ?string $table_identifier = null,
        array $columns_to_skip = []
    ): array {
        $table = $table_type->getTable($table_name_builder, $table_identifier);
        $column_identifiers = match($table_type) {
            TableTypes::TypeSpecificAnswerForms => self::ANSWER_FORM_TABLE_COLUMNS,
            TableTypes::AnswerInputs => self::ANSWER_INPUTS_TABLE_COLUMNS,
            TableTypes::AnswerOptions => self::ANSWER_OPTIONS_TABLE_COLUMNS,
            TableTypes::Additional => self::COMBINATIONS_TABLE_COLUMNS
        };
        return array_map(
            fn(string $v): Column => new Column($table, $v),
            array_filter(
                $column_identifiers,
                fn(string $v) => !in_array($v, $columns_to_skip)
            )
        );
    }

    public function getIdColumn(
        TableNameBuilder $table_name_builder,
        TableTypes $table_type,
        ?string $table_identifier = null
    ): Column {
        return match($table_type) {
            TableTypes::TypeSpecificAnswerForms => new Column(
                $table_type->getTable($table_name_builder),
                self::ANSWER_FORM_TABLE_ID_COLUMN
            ),
            default => new Column(
                $table_type->getTable($table_name_builder, $table_identifier),
                self::ID_COLUMN
            )
        };
    }

    public function completeQuery(
        TableNameBuilder $table_name_builder,
        Query $query,
        Column $base_table_id_column
    ): Query {
        $answer_form_specific_table_definition = TableTypes::TypeSpecificAnswerForms;
        $answer_input_table_definition = TableTypes::AnswerInputs;
        $answer_input_table = $answer_input_table_definition
            ->getTable($table_name_builder);
        $answer_options_table_definition = TableTypes::AnswerOptions;
        $combinations_table_definition = TableTypes::Additional;

        return $query->withAdditionalJoin(
            new Join(
                $base_table_id_column,
                new Column(
                    $answer_form_specific_table_definition->getTable($table_name_builder),
                    self::ANSWER_FORM_TABLE_FOREIGN_KEY_COLUMN
                ),
                JoinType::Left
            )
        )->withAdditionalSelect(
            new Select(
                $this->getColumns($table_name_builder, $answer_form_specific_table_definition)
            )
        )->withAdditionalJoin(
            new Join(
                $base_table_id_column,
                new Column(
                    $answer_input_table,
                    self::ANSWER_INPUTS_TABLE_FOREIGN_KEY_COLUMN
                ),
                JoinType::Left
            )
        )->withAdditionalSelect(
            new Select(
                $this->getColumns($table_name_builder, $answer_input_table_definition)
            )
        )->withAdditionalJoin(
            new Join(
                new Column(
                    $answer_input_table,
                    self::ID_COLUMN
                ),
                new Column(
                    $answer_options_table_definition->getTable($table_name_builder),
                    self::ANSWER_OPTIONS_TABLE_FOREIGN_KEY_COLUMN
                ),
                JoinType::Left
            )
        )->withAdditionalSelect(
            new Select(
                $this->getColumns(
                    $table_name_builder,
                    $answer_input_table_definition
                )
            )
        )->withAdditionalJoin(
            new Join(
                $base_table_id_column,
                new Column(
                    $combinations_table_definition->getTable(
                        $table_name_builder,
                        self::COMBINATIONS_TABLE_IDENTIFIER
                    ),
                    self::COMBINATIONS_TABLE_FOREIGN_KEY_COLUMN
                ),
                JoinType::Left
            )
        )->withAdditionalSelect(
            new Select(
                $this->getColumns(
                    $table_name_builder,
                    $combinations_table_definition,
                    self::COMBINATIONS_TABLE_IDENTIFIER
                )
            )
        );
    }
}
