<?php


namespace FieldEncryption\Connection;


use FieldEncryption\Query\FieldEncryptionBuilder;
use Illuminate\Database\MySqlConnection;

class FieldEncryptionMysqlConnection extends MySqlConnection
{
    /**
     * Get a new query builder instance.
     *
     */
    public function query(): FieldEncryptionBuilder
    {
        return new FieldEncryptionBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }
}
