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
            $this->recreateForeignKey($foreignKey, 'RESTRICT');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->foreignKeys() as $foreignKey) {
            $this->recreateForeignKey($foreignKey, $foreignKey['delete_rule']);
        }
    }

    private function foreignKeys(): array
    {
        return [
            ['table' => 'absences', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'absences_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'absences', 'column' => 'created_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'absences_created_by_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'absences', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'absences_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'adjustments', 'column' => 'approver_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'adjustments_approver_id_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'adjustments', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'adjustments_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'adjustments', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'adjustments_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'announcement_reads', 'column' => 'announcement_id', 'references_table' => 'announcements', 'references_column' => 'id', 'constraint' => 'announcement_reads_announcement_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'announcement_reads', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'announcement_reads_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'announcement_reads', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'announcement_reads_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'announcements', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'announcements_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'announcements', 'column' => 'created_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'announcements_created_by_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'companies', 'column' => 'current_plan_id', 'references_table' => 'plans', 'references_column' => 'id', 'constraint' => 'companies_current_plan_id_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'document_audits', 'column' => 'actor_user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'document_audits_actor_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'document_audits', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'document_audits_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'document_audits', 'column' => 'document_id', 'references_table' => 'documents', 'references_column' => 'id', 'constraint' => 'document_audits_document_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'document_notifications', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'document_notifications_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'document_notifications', 'column' => 'document_id', 'references_table' => 'documents', 'references_column' => 'id', 'constraint' => 'document_notifications_document_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'document_notifications', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'document_notifications_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'documents', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'documents_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'documents', 'column' => 'rejected_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'documents_rejected_by_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'documents', 'column' => 'uploaded_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'documents_uploaded_by_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'documents', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'documents_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'holidays', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'holidays_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'leave_balances', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'leave_balances_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'leave_balances', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'leave_balances_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'leave_policies', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'leave_policies_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'role_user', 'column' => 'role_id', 'references_table' => 'roles', 'references_column' => 'id', 'constraint' => 'role_user_role_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'shift_day_events', 'column' => 'shift_day_id', 'references_table' => 'shift_days', 'references_column' => 'id', 'constraint' => 'shift_day_events_shift_day_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'shift_days', 'column' => 'shift_id', 'references_table' => 'shifts', 'references_column' => 'id', 'constraint' => 'shift_days_shift_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'shifts', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'shifts_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'subscriptions', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'subscriptions_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'subscriptions', 'column' => 'plan_id', 'references_table' => 'plans', 'references_column' => 'id', 'constraint' => 'subscriptions_plan_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'time_entries', 'column' => 'adjustment_requested_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'time_entries_adjustment_requested_by_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'time_entries', 'column' => 'adjustment_reviewed_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'time_entries_adjustment_reviewed_by_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'time_entries', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'time_entries_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'time_entries', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'time_entries_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'time_entries', 'column' => 'user_shift_id', 'references_table' => 'user_shifts', 'references_column' => 'id', 'constraint' => 'time_entries_user_shift_id_foreign', 'delete_rule' => 'SET NULL'],
            ['table' => 'user_leave_policies', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'user_leave_policies_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'user_leave_policies', 'column' => 'leave_policy_id', 'references_table' => 'leave_policies', 'references_column' => 'id', 'constraint' => 'user_leave_policies_leave_policy_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'user_leave_policies', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'user_leave_policies_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'user_shifts', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'user_shifts_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'user_shifts', 'column' => 'shift_id', 'references_table' => 'shifts', 'references_column' => 'id', 'constraint' => 'user_shifts_shift_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'user_shifts', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'user_shifts_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'users', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'users_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_days', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'vacation_days_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_days', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'vacation_days_user_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_days', 'column' => 'vacation_request_id', 'references_table' => 'vacation_requests', 'references_column' => 'id', 'constraint' => 'vacation_days_vacation_request_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_requests', 'column' => 'approved_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'vacation_requests_approved_by_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_requests', 'column' => 'company_id', 'references_table' => 'companies', 'references_column' => 'id', 'constraint' => 'vacation_requests_company_id_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_requests', 'column' => 'requested_by', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'vacation_requests_requested_by_foreign', 'delete_rule' => 'CASCADE'],
            ['table' => 'vacation_requests', 'column' => 'user_id', 'references_table' => 'users', 'references_column' => 'id', 'constraint' => 'vacation_requests_user_id_foreign', 'delete_rule' => 'CASCADE'],
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
            "ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) " .
            "REFERENCES {$referencesTable} ({$referencesColumn}) ON DELETE {$deleteRule}"
        );
    }

    private function quoteIdentifier(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
};
