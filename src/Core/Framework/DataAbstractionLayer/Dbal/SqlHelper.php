<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal;


/**
 * @description This class is used to build a select part of a SQL.
 *
 * @final
 */
class SqlHelper
{
    /**
     * @description This function is used to build the json_object of the select part of a query.
     *
     * @param array<string, string> $fields The key is the alias of the column, the value is the column itself
     *
     * @example
     *
     * $fields = [ 'id' => 'blog.id', 'name' => 'blog.name' ];
     * $select = EntityDefinitionQueryHelper::object($fields, 'blog');
     * // $select = "JSON_OBJECT('id', blog.id, 'name', blog.name) as blog";
     */
    public static function object(array $fields, string $alias): string
    {
        $columnSelectSql = '';

        foreach ($fields as $columnAlias => $column) {
            $columnSelectSql .= \sprintf('\'%s\', %s,', $columnAlias, $column);
        }

        $columnSelectSql = rtrim($columnSelectSql, ',');

        $sql = 'JSON_OBJECT(%s) as %s';

        return \sprintf($sql, $columnSelectSql, $alias);
    }

    /**
     * @description This function is used to build the json_array of the select part of a query.
     *
     * @param array<int|string, string> $fields The key is the alias of the column, the value is the column itself
     *
     * @example
     *
     * $fields = [ 'id' => 'tags.id', 'name' => 'tags.name' ];
     * $select = EntityDefinitionQueryHelper::object($fields, 'tags');
     * // $select = "CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT('id', tags.id, 'name', tags.name)), ']') as tags";
     */
    public static function objectArray(array $fields, string $alias): string
    {
        $columnSelectSql = '';

        foreach ($fields as $columnAlias => $column) {
            $columnSelectSql .= \sprintf('\'%s\', %s,', $columnAlias, $column);
        }

        $columnSelectSql = rtrim($columnSelectSql, ',');

        $sql = <<<EOF
CONCAT(
    '[',
         GROUP_CONCAT(DISTINCT
             JSON_OBJECT(
                %s
             )
         ),
    ']'
) as %s
EOF;

        return \sprintf($sql, $columnSelectSql, $alias);
    }
}
