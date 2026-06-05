<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentReviewUploadForEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_uploads_employee_document_to_private_s3_and_persists_metadata(): void
    {
        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $employee = User::factory()->create([
            'company_id' => $admin->company_id,
        ]);
        $employee->syncRoles(['employee']);

        Sanctum::actingAs($admin, ['*']);
        Storage::fake('s3');

        $file = UploadedFile::fake()->createWithContent(
            'holerite.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
        );

        $response = $this->postJson('/v1/admin/documents/upload-for-employee', [
            'user_id' => $employee->id,
            'category' => Document::CATEGORY_PAYROLL,
            'title' => 'Comprovante',
            'notes' => 'Upload administrativo',
            'files' => [$file],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.0.original_name', 'holerite.pdf')
            ->assertJsonPath('data.0.storage_disk', 's3')
            ->assertJsonPath('data.0.mime_type', 'application/pdf');

        $documentId = $response->json('data.0.id');
        $this->assertNotEmpty($documentId);

        $document = Document::query()->findOrFail($documentId);

        $expectedPrefix = "jornafy-documents/{$admin->company_id}/documents/employees/{$employee->id}/";
        $this->assertStringStartsWith($expectedPrefix, $document->path);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}\.pdf$/', basename($document->path));

        Storage::disk('s3')->assertExists($document->path);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'company_id' => $admin->company_id,
            'user_id' => $employee->id,
            'storage_disk' => 's3',
            'path' => $document->path,
            'original_name' => 'holerite.pdf',
            'uploaded_by' => $admin->id,
        ]);
    }
}
