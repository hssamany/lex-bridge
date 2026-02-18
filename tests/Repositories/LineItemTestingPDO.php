<?php
declare(strict_types=1);

namespace Tests\Repositories;

use PDO;
use PDOStatement;

/**
 * SQLite PDO for testing, replaces NOW() with CURRENT_TIMESTAMP
 */
final class LineItemTestingPDO extends PDO
{
    public $rollbackCalled = false;
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function rollBack(): bool {
        $this->rollbackCalled = true;
        return parent::rollBack();
    }

    public function prepare(string $statement, array $options = []): PDOStatement|false
    {
        $statement = $this->transformSql($statement);
        return parent::prepare($statement);
    }

    private function transformSql(string $sql): string
    {
        return (string) preg_replace('/NOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);
    }
}
