<?php


namespace FieldEncryption\Command;

use FieldEncryption\Utils\DecryptUtils;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SysCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = "sys:filed-encryption";

    protected $description = "同步|还原数据库字段加密";

    /**
     * 处理数据库字段加密 同步 和 还原
     */
    public function handle()
    {
        $this->info('同步|还原数据库加密,需要保证以下几点' . "\n\t"
            . "1.拥有数据库创建临时表权限" . "\n\t"
            . "2.拥有数据库读写权限" . "\n\t"
            . "3.保证配置信息 config(field_encryption.table_tmp_switch) 为true,同步|还原 结束之后最好改回 false" . "\n\t"
            . "4.保证配置信息 config(field_encryption.table_tmp) 尽可能奇怪复杂,以它为后缀进行创建临时表不要与原来表名称冲突" . "\n\t"
            . "5.待加密字段在表中为varchar类型并且字段要足够长 varchar(256) 或者 varchar(512)" . "\n\t"
        );
        $type = (int)$this->ask('请选择：' . "\n\t" . '1.同步' . "\n\t" . '2.还原');

        if ($type === 1) {
            $this->sysFieldEncryption();
        }

        if ($type === 2) {
            $this->reductionFieldEncryption();
        }
    }

    /**
     * 数据字段加密
     */
    public function sysFieldEncryption(): void
    {
        $field = config('field_encryption.field') ?? [];
        $tableTmpTail = config('field_encryption.table_tmp', '_tmp_xxx_123123');
        if ($field) {
            $tables = collect($field)->pluck('table')->unique()->values()->toArray();
            $askTable = $this->ask('请输入需要同步的表:(以下表名称)，若全部表请输入:ALL，否则输入表名称' . "\n\t" . implode("\n\t", $tables));
            if (strtolower($askTable) === 'all') {
                $tables = collect($field)->pluck('table')->unique()->values()->toArray();
            } else {
                $tables = collect($field)->where('table', $askTable)->pluck('table')->unique()->values()->toArray();
            }
            if ($tables) {
                foreach ($tables as $table) {
                    $columns = collect($field)->where('table', $table)->pluck('column')->unique()->values()->toArray();
                    $temporarySql = "CREATE TEMPORARY TABLE IF NOT EXISTS " . $table . $tableTmpTail . " (
                     `id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
                    if ($columns && is_array($columns)) {
                        foreach ($columns as $column) {
                            $temporarySql .= "{$column} varchar(256) COLLATE utf8mb4_unicode_ci,";
                        }
                    }
                    $temporarySql .= "PRIMARY KEY (`id`) USING BTREE)";
                    DB::statement($temporarySql);

                    $values = DB::table($table)->get()->toArray();
                    if ($values) {
                        $insert = collect($values)->map(function ($value) use ($columns) {
                            return collect($value)->only(array_merge($columns, ['id']))->toArray();
                        })->toArray();
                        $db = DB::table($table . $tableTmpTail);
                        $this->batchInsert($db, $insert, 200);
                        $updateValue = [];
                        if ($columns && is_array($columns)) {
                            foreach ($columns as $column) {
                                $updateValue['a.' . $column] = DB::raw('b.' . $column);
                            }
                        }

                        DB::table($table . ' as a')
                            ->leftJoin($table . $tableTmpTail . ' as b', 'a.id', '=', 'b.id')
                            ->update($updateValue);
                    }
                    $this->info('table:' . $table . ',迁移完成');
                }
            } else {
                $this->error('无法查到对应表信息:' . $askTable);
            }
        }
    }

    /**
     * 还原加密字段
     */
    public function reductionFieldEncryption(): void
    {
        $field = config('field_encryption.field') ?? [];
        $tableTmpTail = '_tmp_xxx_121314';
        /** @var DecryptUtils $decrypt */
        $decrypt = app(DecryptUtils::class);
        if ($field) {
            $tables = collect($field)->pluck('table')->unique()->values()->toArray();
            $askTable = $this->ask('请输入需要还原的表:(以下表名称)，若全部表请输入:ALL，否则输入表名称' . "\n\t" . implode("\n\t", $tables));
            if (strtolower($askTable) === 'all') {
                $tables = collect($field)->pluck('table')->unique()->values()->toArray();
            } else {
                $tables = collect($field)->where('table', $askTable)->pluck('table')->unique()->values()->toArray();
            }
            if ($tables) {
                foreach ($tables as $table) {
                    $columns = collect($field)->where('table', $table)->pluck('column')->unique()->values()->toArray();
                    $temporarySql = "CREATE TEMPORARY TABLE IF NOT EXISTS " . $table . $tableTmpTail . " (
                     `id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
                    if ($columns && is_array($columns)) {
                        foreach ($columns as $column) {
                            $temporarySql .= "{$column} varchar(256) COLLATE utf8mb4_unicode_ci,";
                        }
                    }
                    $temporarySql .= "PRIMARY KEY (`id`) USING BTREE)";
                    DB::statement($temporarySql);

                    $values = DB::table($table)->get()->toArray();
                    if ($values) {
                        $insert = collect($values)->map(function ($value) use ($columns, $decrypt) {
                            foreach ($value as $key => $item) {
                                if (in_array($key, $columns, false)) {
                                    $value[$key] = $decrypt->decryptAes($item);
                                }
                            }
                            return collect($value)->only(array_merge($columns, ['id']))->toArray();
                        })->toArray();
                        $db = DB::table($table . $tableTmpTail);
                        $this->batchInsert($db, $insert, 200);
                        $updateValue = [];
                        if ($columns && is_array($columns)) {
                            foreach ($columns as $column) {
                                $updateValue['a.' . $column] = DB::raw('b.' . $column);
                            }
                        }

                        DB::table($table . ' as a')
                            ->leftJoin($table . $tableTmpTail . ' as b', 'a.id', '=', 'b.id')
                            ->update($updateValue);
                    }
                    $this->info('table:' . $table . ',迁移完成');
                }
            } else {
                $this->error('无法查到对应表信息:' . $askTable);
            }
        }
    }

    /**
     * 针对数据库字段
     * @param Model|Builder $model
     * @param array $insertArray
     * @param int $size
     * @return int
     */
    public function batchInsert($model, array $insertArray, int $size = 1000): int
    {
        $number = 0;
        if ($insertArray) {
            foreach (array_chunk($insertArray, $size) as $insert) {
                $insertModel = clone $model;
                if ($insertModel->insert($insert)) {
                    $number += count($insert);
                }
            }
        }
        return $number;
    }

}
