<?php


namespace FieldEncryption\Query;

use Closure;
use FieldEncryption\Utils\ConfigUtils;
use FieldEncryption\Utils\DecryptUtils;
use FieldEncryption\Utils\EncryptionUtils;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FieldEncryptionBuilder extends Builder
{

    protected $tableName;
    protected $tableAlias;
    protected $judgeEncryption = self::JUDGE_ENCRYPTION_NORMAL;

    public const JUDGE_ENCRYPTION_FALSE = 0;
    public const JUDGE_ENCRYPTION_TRUE = 1;
    public const JUDGE_ENCRYPTION_NORMAL = 2;

    /**
     * Set the table which the query is targeting.
     *
     * @param \Closure|Builder|string $table
     * @param string|null $as
     * @return $this
     */
    public function from($table, $as = null)
    {
        if ($this->isQueryable($table)) {
            return $this->fromSub($table, $as);
        }

        $this->from = $as ? "{$table} as {$as}" : $table;
        $this->tableName = $this->getTableName($table, $as);
        $this->tableAlias = $this->getTableAlias($table, $as);
        return $this;
    }

    /**
     * 获取表名称
     * @param $table
     * @param null $as
     * @return string|null
     */
    public function getTableName($table, $as = null): ?string
    {
        $from = $as ? "{$table} as {$as}" : $table;
        return $as ? $table : $this->stripAlias($from);
    }

    /**
     * 获取表别名
     * @param $table
     * @param null $as
     * @return mixed|string
     */
    public function getTableAlias($table, $as = null)
    {
        $from = $as ? "{$table} as {$as}" : $table;
        return $as ?: $this->getTableAliasStripTable($from);
    }

    /**
     * Add a join clause to the query.
     *
     * @param string $table
     * @param \Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @param bool $where
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $join = $this->newJoinClause($this, $type, $table);
        //修改项，添加join关联查询的表的实际的表名称和表别名
        $join->tableName = $this->getTableName($table);
        $join->tableAlias = $this->getTableAlias($table);
        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof Closure) {
            $first($join);

            $this->joins[] = $join;

            $this->addBinding($join->getBindings(), 'join');
        }

        // If the column is simply a string, we can assume the join simply has a basic
        // "on" clause with a single condition. So we will just build the join with
        // this simple join clauses attached to it. There is not a join callback.
        else {
            $method = $where ? 'where' : 'on';

            $this->joins[] = $join->$method($first, $operator, $second);

            $this->addBinding($join->getBindings(), 'join');
        }

        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $values
     * @return bool
     */
    public function insert(array $values): bool
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        //针对value 值进行加密
        $this->encryptionValues($values, 2);
        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record into the database while ignoring errors.
     *
     * @param array $values
     * @return int
     */
    public function insertOrIgnore(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        //针对value 值进行加密
        $this->encryptionValues($values, 2);

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertOrIgnore($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param array $values
     * @param string|null $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null): int
    {
        //针对value 值进行加密
        $this->encryptionValues($values, 1);

        return parent::insertGetId($values, $sequence);
    }

    /**
     * Update a record in the database.
     *
     * @param array $values
     * @return int
     */
    public function update(array $values): int
    {
        //针对value 值进行加密
        $this->encryptionValues($values, 1);

        return parent::update($values);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param \Closure|string|array $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return Builder
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $type = 'Basic';

        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        if (Str::contains($column, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');

            if (is_string($column)) {
                $type = 'JsonBoolean';
            }
        }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.

        $this->encryptionValueByWhere($operator, $column, $value);

        if ($fileBuilder = $this->encryptionValueByWhereLike($operator, $column, $value, $boolean)) {
            return $fileBuilder;
        }

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        if (!$value instanceof Expression) {
            $this->addBinding($this->flattenValue($value), 'where');
        }

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @param bool $not
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        //针对value 值进行加密
        $this->encryptionValueByWhereIn($column, $values);

        // If the value is a query builder instance we will assume the developer wants to
        // look for any values that exists within this given query. So we will add the
        // query accordingly so that this query is properly executed when it is run.
        if ($this->isQueryable($values)) {
            [$query, $bindings] = $this->createSub($values);

            $values = [new Expression($query)];

            $this->addBinding($bindings, 'where');
        }

        // Next, if the value is Arrayable we need to cast it to its raw array form so we
        // have the underlying array value instead of an Arrayable object which is not
        // able to be added as a binding, etc. We will then add to the wheres array.
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // Finally we'll add a binding for each values unless that value is an expression
        // in which case we will just skip over it since it will be the query as a raw
        // string and not as a parameterized place-holder to be replaced by the PDO.
        $this->addBinding($this->cleanBindings($values), 'where');

        return $this;
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect(): array
    {
        $arr = parent::runSelect();
        $this->decryptGet($arr);
        return $arr;
    }

    /**
     * 针对查询出来的数据进行解密
     * @param $gets
     */
    protected function decryptGet(&$gets): void
    {
        //判断是否有内容 并且表名或关联表 在加密配置上有配置
        if ($gets && $this->judgeEncryption()) {
            /** @var DecryptUtils $decrypt */
            $decrypt = app(DecryptUtils::class);
            /** @var ConfigUtils $configUtils */
            $configUtils = app(ConfigUtils::class);
            //todo 目前暂时先获取所有配置字段进行判断是否解密处理，针对 别名中的数据并未进行处理
            $configColumns = $configUtils->getAllFields();
            foreach ($gets as &$get) {
                if ($get && is_array($get)) {
                    foreach ($get as $column => $value) {
                        if ($value && is_string($value) && in_array($column, $configColumns, false)) {
                            $get[$column] = $decrypt->decryptAes($value);
                        }
                    }
                }
            }
        }
    }

    /**
     * 判断是否需要进行加密（针对主表名称 和 关联表名称 是否在 加密配置表中）
     * @return bool
     */
    public function judgeEncryption(): bool
    {
        if ($this->judgeEncryption === self::JUDGE_ENCRYPTION_NORMAL) {
            /** @var ConfigUtils $configUtil */
            $configUtil = app(ConfigUtils::class);
            $tableNames = [];
            if ($this->joins) {
                $tableNames = collect($this->joins)->pluck('tableName')->unique()->toArray();
            }
            $tableNames[] = $this->tableName;
            if ($configUtil->judgeTableByTables($tableNames)) {
                $this->judgeEncryption = self::JUDGE_ENCRYPTION_TRUE;
            } else {
                $this->judgeEncryption = self::JUDGE_ENCRYPTION_FALSE;
            }
        }
        return $this->judgeEncryption === self::JUDGE_ENCRYPTION_TRUE;
    }

    /**
     * 根据 $column 内容获取 对应的表名称 和 列名称 如： $column = "tableName.column" or $column = "tableAlias.column"
     * 得到 [tableName, column], 如果 tableName 和 column 没有在加密配置中 返回[null, null] 否则 返回 [tableName, column]
     * @param $column
     * @return array
     */
    public function getTableColumnByEncryption($column): array
    {
        $columnTableOrAlias = $this->stripColumnGetTableOrAlias($column);
        $column = $this->stripTableForPluck($column);
        $tables = [$this->tableName => $this->tableAlias];
        if ($this->joins) {
            $tables = array_merge(collect($this->joins)->pluck('tableAlias', 'tableName')->unique()->toArray(), $tables);
        }
        /** @var ConfigUtils $configUtil */
        $configUtil = app(ConfigUtils::class);

        if ($tables) {
            if ($columnTableOrAlias) {
                foreach ($tables as $tableName => $alias) {
                    if ($alias === $columnTableOrAlias || $tableName === $columnTableOrAlias) {
                        if ($fields = $configUtil->getFieldEncryptionField($tableName, $column)) {
                            return [$fields['table'] ?? null, $fields['column'] ?? null];
                        }
                        return [null, null];
                    }
                }
            } else {
                return $configUtil->getTableColumnByTablesColumn(array_keys($tables), $column);
            }
        }
        return [null, null];
    }

    /**
     * Strip off the alias from a table name identifier.
     *
     * eg: $tableName = "tableName as tableAlias" , $tableName = "tableName"
     *     return "tableName", return "tableName"
     * @param string $tableName
     * @return string|null
     */
    protected function stripAlias(string $tableName): ?string
    {
        if (is_null($tableName)) {
            return null;
        }
        if (stripos($tableName, ' as ')) {
            return trim(head(preg_split('~' . ' as ' . '~i', $tableName)));
        }
        return $tableName;
    }

    /**
     * get alias from a table name identifier.
     * eg: $tableName = "tableName as tableAlias" , $tableName = "tableName"
     *     return "tableAlias", return ""
     * @param string $tableName
     * @return string
     */
    protected function getTableAliasStripTable(string $tableName): string
    {
        if (is_null($tableName)) {
            return "";
        }
        if (stripos($tableName, ' as ')) {
            return trim(last(preg_split('~' . ' as ' . '~i', $tableName)));
        }
        return "";
    }

    /**
     * Strip off the table name or alias from a column identifier.
     * eg: $column = "tableName.column" , $column = "tableAlias.column"
     *     return tableName , return tableAlias
     * @param string $column
     * @return string
     */
    protected function stripColumnGetTableOrAlias(string $column): string
    {
        if (is_null($column)) {
            return "";
        }

        $separator = strpos($column, '.');
        if ($separator) {
            return trim(head(preg_split('~' . '\.' . '~i', $column)));
        }
        return "";
    }


    /**
     * 对 where = 的数据进行加密，如果存在加密字段,则将字段进行加密后再进行后续处理 where($column, encrypt($value))
     * @param $operator
     * @param $column
     * @param $value
     */
    public function encryptionValueByWhere($operator, $column, &$value): void
    {
        if (!$this->judgeEncryption()) {
            return;
        }

        if ($operator === '=') {
            [$table, $tmpColumn] = $this->getTableColumnByEncryption($column);
            if ($table && $tmpColumn) {
                $value = $this->encryptionValueByColumn($table, $tmpColumn, $value);
            }
        }
    }

    /**
     *
     * 对 where like 的数据进行加密，加密字段一般不支持模糊查询，这里只是保留前置字段和后置字段有可能进行模糊查询的时候能进行匹配
     * 并且一般模糊查询的时候无法查询精确数据
     * 所以改造关于加密字段的where like 查询包括（column, 'like', 'value'） （column, 'like', '%value%'） （column, 'like', '%value'） （column, 'like', 'value%'）
     * 即更改成查询 where(function($query) { $query->orWere(column, 'like', '[%]value[%]')->orWhere('column', encrypt(value))})
     * @param $operator
     * @param $column
     * @param $value
     * @param string $boolean
     * @return FieldEncryptionBuilder|Builder|null
     */
    public function encryptionValueByWhereLike($operator, $column, &$value, string $boolean = 'and')
    {
        //避免where like 递归查询 加标识进行判断是否已经处理过数据
        $encryptionIdentificationPre = 'encryption_xxx-sss';
        if ($operator === 'like') {
            if (mb_strpos($value, $encryptionIdentificationPre) === 0) {
                $value = mb_substr($value, mb_strlen($encryptionIdentificationPre));
            } else {
                [$table, $tmpColumn] = $this->getTableColumnByEncryption($column);
                if ($table && $tmpColumn) {
                    $tmpValue = $encryptionIdentificationPre. $value;
                    $whereValue = $value;
                    //正则匹配对应内容 %whereValue%, %whereValue, whereValue%
                    preg_match("/(?<=%)(.*?)(?=%)/", $whereValue, $matches);
                    if (!$matches) {
                        preg_match("/(?<=^%)(.*$)/", $whereValue, $matches);
                        if (!$matches) {
                            preg_match("/(.*)(?=%$)/", $whereValue, $matches);
                        }
                    }

                    if (isset($matches[0])) {
                        $whereValue = $matches[0];
                    }

                    return $this->whereNested(function ($query) use ($column, $whereValue, $tmpValue) {
                        $query->where($column, 'like', $tmpValue)
                        ->orWhere($column, $whereValue);
                    }, $boolean);
                }
            }
        }
        return null;
    }

    /**
     * 对 where in 的数据进行加密，如果存在加密字段,则将字段进行加密后再进行后续处理 where($column, encrypt($value))
     * @param $column
     * @param $values
     */
    public function encryptionValueByWhereIn($column, &$values): void
    {
        if (!$this->judgeEncryption()) {
            return;
        }
        [$table, $column] = $this->getTableColumnByEncryption($column);

        if ($table && $column && $values && is_array($values)) {
            $tmpValues = [];
            foreach ($values as $item) {
                $tmpValues[] = $this->encryptionValueByColumn($table, $column, $item);
            }
            $values = $tmpValues;
        }
    }

    /**
     * 将某一列对应的value 进行加密
     * @param $table
     * @param $column
     * @param $value
     * @return mixed|string
     */
    public function encryptionValueByColumn($table, $column, $value)
    {
        /** @var EncryptionUtils $encryption */
        $encryption = app(EncryptionUtils::class);
        /** @var ConfigUtils $configUtil */
        $configUtil = app(ConfigUtils::class);
        if (is_string($column)) {
            $field = $configUtil->getFieldEncryptionField($table, $column);
            if ($field) {
                $rule = $configUtil->getFieldEncryptionRule($field['rule'] ?? '');
                if ($rule && isset($rule['pre_len'], $rule['tail_len'], $rule['encryption_field_len'])) {
                    $value = $encryption->encryptionAes($value, $rule['pre_len'], $rule['encryption_field_len']);
                }
            }
        }
        return $value;
    }

    /**
     * 对1维数组2维数组对应的字段进行加密
     * @param $values
     * @param int $dimension
     */
    public function encryptionValues(&$values, int $dimension = 1): void
    {
        if (!$this->judgeEncryption()) {
            return;
        }
        //todo 此方法用于insert or update 暂时只考虑table表 忽略此join关联表信息,如果有特殊要求的话可以进行更改
        $table = $this->tableName;
        /** @var ConfigUtils $configUtils */
        $configUtils = app(ConfigUtils::class);
        $fields = $configUtils->getFieldEncryptionFieldsByTable($table);
        $types = [];
        if ($fields) {
            foreach ($fields as $field) {
                if (!array_key_exists($field['rule'], $types)) {
                    $types[$field['rule']] = [];
                }
                $types[$field['rule']][] = $field['column'];
            }
        }

        if ($types) {
            if ($dimension === 1) {
                $this->encryptionValuesItem($values, $types);
            }

            if ($dimension === 2) {
                $values = collect($values)->map(function ($item) use ($types) {
                    $this->encryptionValuesItem($item, $types);
                    return $item;
                })->toArray();
            }
        }
    }

    /**
     * 针对不同加密规则对数据进行加密
     * @param $item
     * @param $types
     */
    public function encryptionValuesItem(&$item, $types): void
    {
        /** @var EncryptionUtils $encryption */
        $encryption = app(EncryptionUtils::class);
        /** @var ConfigUtils $configUtils */
        $configUtils = app(ConfigUtils::class);
        if ($types && is_array($types)) {
            foreach ($types as $ruleKey => $ruleColumns) {
                if ($ruleColumns && is_array($ruleColumns)) {
                    $rule = $configUtils->getFieldEncryptionRule($ruleKey);
                    foreach ($ruleColumns as $column) {
                        if (isset($item[$column], $rule['pre_len'], $rule['tail_len'], $rule['encryption_field_len']) && $rule && $ruleKey) {
                            $item[$column] = $encryption->encryptionAes($item[$column], $rule['pre_len'], $rule['encryption_field_len']);
                        }
                    }
                }
            }
        }
    }
}
