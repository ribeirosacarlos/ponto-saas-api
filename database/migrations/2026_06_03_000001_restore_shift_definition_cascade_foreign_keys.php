<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->foreignKeys() as $foreignKey) {
            $this->recreateForeignKey($foreignKey, 'CASCADE');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->foreignKeys() as $foreignKey) {
            $this->recreateForeignKey($foreignKey, 'RESTRICT');
        }
    }

    private function foreignKeys(): array
    {
        return [
            [
                'table' => 'shift_day_events',
                'column' => 'shift_day_id',
                'references_table' => 'shift_days',
                'references_column' => 'id',
                'constraint' => 'shift_day_events_shift_day_id_foreign',
            ],
            [
                'table' => 'shift_days',
                'column' => 'shift_id',
                'references_table' => 'shifts',
                'references_column' => 'id',
                'constraint' => 'shift_days_shift_id_foreign',
            ],
        ];
    }

    private function recreateForeignKey(array $foreignKey, string $deleteRule): void
    {
        $table = $this->quoteIdentifier($foreignKey['table']);
        $column = $this->quoteIdentifier($foreignKey['column']);
        $referencesTable = $this->quoteIdentifier($foreignKey['references_table']);
        $referencesColumn = $this->quoteIdentifier($foreignKey['references_column']);
        $constraint = $this->quoteIdentifier($foreignKey['constraint']);

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) ".
            "REFERENCES {$referencesTable} ({$referencesColumn}) ON DELETE {$deleteRule}"
        );
    }

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
};
