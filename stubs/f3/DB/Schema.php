<?php

namespace DB\SQL;

/**
 * F3 DB\SQL\Schema — schema builder.
 */
class Schema {
    const DT_BOOL = 'BOOLEAN';
    const DT_BOOLEAN = 'BOOLEAN';
    const DT_INT1 = 'INT1';
    const DT_TINYINT = 'INT1';
    const DT_INT2 = 'INT2';
    const DT_SMALLINT = 'INT2';
    const DT_INT4 = 'INT4';
    const DT_INT = 'INT4';
    const DT_INT8 = 'INT8';
    const DT_BIGINT = 'INT8';
    const DT_FLOAT = 'FLOAT';
    const DT_DOUBLE = 'DOUBLE';
    const DT_DECIMAL = 'DOUBLE';
    const DT_VARCHAR128 = 'VARCHAR128';
    const DT_VARCHAR256 = 'VARCHAR256';
    const DT_VARCHAR512 = 'VARCHAR512';
    const DT_TEXT = 'TEXT';
    const DT_LONGTEXT = 'LONGTEXT';
    const DT_DATE = 'DATE';
    const DT_DATETIME = 'DATETIME';
    const DT_TIMESTAMP = 'TIMESTAMP';
    const DT_BLOB = 'BLOB';
    const DT_BINARY = 'BLOB';
    const DF_CURRENT_TIMESTAMP = 'CUR_STAMP';

    public function __construct(\DB\SQL $db) {}
    /** @return array<string> */
    public function getDatabases(): array {}
    /** @return array<string> */
    public function getTables(): array {}
    public function createTable(string $name): \DB\SQL\TableBuilder {}
    public function alterTable(string $name): \DB\SQL\TableModifier {}
    /** @return string|array<mixed> */
    public function renameTable(string $name, string $new_name, bool $exec = true): string|array {}
    /** @return string|array<mixed> */
    public function dropTable(string $name, bool $exec = true): string|array {}
    /** @return string|array<mixed> */
    public function truncateTable(string $name, bool $exec = true): string|array {}
}

abstract class TableBuilder {
    public function __construct(string $name, \DB\SQL\Schema $schema) {}
    /** @return string|array<mixed> */
    abstract public function build(bool $exec = true): string|array;
    /** @param array<string, mixed>|null $args */
    public function addColumn(string $key, ?array $args = null): \DB\SQL\Column {}
    /** @param array<string> $pkeys */
    public function primary(array $pkeys): static {}
    public function setCharset(string $charset, string $collation = 'unicode'): static {}
    /** @param array<string>|string $columns */
    public function addIndex(array|string $columns, bool $unique = false, int $length = 20): static {}
}

class TableModifier extends TableBuilder {
    /** @return string|array<mixed> */
    public function build(bool $exec = true): string|array {}
    /** @return array<string, mixed> */
    public function getCols(bool $types = false): array {}
    public function dropColumn(string $name): static {}
    public function renameColumn(string $name, string $new_name): static {}
    public function updateColumn(string $name, string $datatype, bool $force = false): static {}
    public function dropIndex(string $name): static {}
    /** @return array<mixed> */
    public function listIndex(): array {}
    /** @return string|array<mixed> */
    public function rename(string $new_name, bool $exec = true): string|array {}
    /** @return string|array<mixed> */
    public function drop(bool $exec = true): string|array {}
}

class Column {
    public function __construct(string $name, \DB\SQL\TableBuilder $table) {}
    public function type(string $datatype, bool $force = false): static {}
    public function nullable(bool $nullable): static {}
    public function defaults(mixed $default): static {}
    public function after(string $name): static {}
    public function index(bool $unique = false): static {}
    public function type_int(): static {}
    public function type_varchar(int $length = 255): static {}
    public function type_text(): static {}
    public function type_bool(): static {}
    public function type_timestamp(bool $asDefault = false): static {}
    public function type_datetime(): static {}
    public function type_float(): static {}
    public function type_bigint(): static {}
}
