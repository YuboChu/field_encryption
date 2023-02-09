<?php


namespace FieldEncryption\Utils;


class ConfigUtils
{
    public function __construct($config)
    {
        $this->config = $config;
    }

    public $config;
    /**
     * 返回对应加密规则
     * @param string $type
     * @return array|mixed
     */
    public function getFieldEncryptionRule(string $type = 'default')
    {
        $rules = $this->config['rules'] ?? [];
        return $rules[$type] ?? [];
    }

    public function getTableTmpTail()
    {
        return $this->config['table_tmp'] ?? '_tmp_xxx_123123';
    }

    /**
     * @return false|mixed
     */
    public function judgeTableTmp()
    {
        return $this->config['table_tmp_switch'] ?? false;
    }

    /**
     * 根据表名和键值 找到对应配置
     * @param $table
     * @param $key
     * @return array
     */
    public function getFieldEncryptionField($table, $key): array
    {

        $fieldConf = $this->config['field'] ?? [];

        $field = collect($fieldConf)->where('table', $table)
            ->where('column', $key)
            ->first();

        if (!$field && $this->judgeTableTmp()) {
            $tableTempTail = $this->getTableTmpTail();
            $table_tmp = mb_substr($table, mb_strlen($table) - mb_strlen($tableTempTail) , mb_strlen($tableTempTail), 'utf-8');
            if ($table_tmp === $tableTempTail) {
                collect($fieldConf)->where('table', mb_substr($table, 0 , -mb_strlen($tableTempTail), 'utf-8'))
                    ->where('column', $key)
                    ->first();
            }
        }

        if ($field) {
            if (!is_array($field)) {
                return $field->toArray();
            }
            return $field;
        }
        return [];
    }

    /**
     * 根据表名找到 对应配置
     * @param $table
     * @return array
     */
    public function getFieldEncryptionFieldsByTable($table): array
    {
        $fieldConf = $this->config['field'] ?? [];

        $fieldEncryptionFields = collect($fieldConf)->where('table', $table)->all();
        if (!$fieldEncryptionFields && $this->judgeTableTmp()) {
            $tableTempTail = $this->getTableTmpTail();
            $table_tmp = mb_substr($table, mb_strlen($table) - mb_strlen($tableTempTail) , mb_strlen($tableTempTail), 'utf-8');
            if ($table_tmp === $tableTempTail) {
                $fieldEncryptionFields = collect($fieldConf)->where('table', mb_substr($table, 0 , -mb_strlen($tableTempTail), 'utf-8'))
                    ->all();
            }
        }
        return $fieldEncryptionFields;
    }

    /**
     * @param array $tables
     * @return bool
     */
    public function judgeTableByTables(array $tables): bool
    {
        $fieldConf = $this->config['field'] ?? [];
        $fieldEncryptionFields = collect($fieldConf)->whereIn('table', $tables)->all();
        if ($fieldEncryptionFields) {
            return true;
        }

        if ($this->judgeTableTmp()) {
            $tableTempTail = $this->getTableTmpTail();
            $tables = collect($tables)->map(function ($table) use ($tableTempTail) {
                $table_tmp = mb_substr($table, mb_strlen($table) - mb_strlen($tableTempTail) , mb_strlen($tableTempTail), 'utf-8');
                if ($table_tmp === $tableTempTail) {
                    $table = mb_substr($table, 0 , -mb_strlen($tableTempTail, 'utf-8'));
                }
                return $table;
            })->toArray();
            $fieldEncryptionFields = collect($fieldConf)->whereIn('table', $tables)->all();
            if ($fieldEncryptionFields) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $tables
     * @param $column
     * @return array
     */
    public function getTableColumnByTablesColumn(array $tables, $column): array
    {
        $fieldConf = $this->config['field'] ?? [];
        $fieldEncryptionFields = collect($fieldConf)->whereIn('table', $tables)->where('column', $column)->all();
        if ($fieldEncryptionFields) {
            $fieldEncryptionFields = array_values($fieldEncryptionFields);
            return [$fieldEncryptionFields[0]['table'] ?? null, $fieldEncryptionFields[0]['column'] ?? null];
        }

        if ($this->judgeTableTmp()) {
            $tableTempTail = $this->getTableTmpTail();
            $tables = collect($tables)->map(function ($table) use ($tableTempTail) {
                $table_tmp = mb_substr($table, mb_strlen($table) - mb_strlen($tableTempTail) , mb_strlen($tableTempTail), 'utf-8');
                if ($table_tmp === $tableTempTail) {
                    $table = mb_substr($table, 0 , -mb_strlen($tableTempTail, 'utf-8'));
                }
                return $table;
            })->toArray();
            $fieldEncryptionFields = collect($fieldConf)->whereIn('table', $tables)->where('column', $column)->all();
            if ($fieldEncryptionFields) {
                $fieldEncryptionFields = array_values($fieldEncryptionFields);
                return [$fieldEncryptionFields[0]['table'] ?? null, $fieldEncryptionFields[0]['column'] ?? null];
            }
        }
        return [null, null];
    }


    /**
     * @return array
     */
    public function getAllFields(): array
    {
        $fieldConf = $this->config['field'] ?? [];
        return collect($fieldConf)->pluck('column')->unique()->toArray();
    }

}
