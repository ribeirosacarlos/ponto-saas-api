<?php

namespace Tests\Feature\Documents;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DocumentS3FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    }

    public function test_employee_store_uploads_document_to_private_s3_and_persists_metadata(): void
    {
        $employee = $this->createEmployee();

        Sanctum::actingAs($employee, ['*']);
        Storage::fake('s3');

        $file = $this->fakePdf('contrato.pdf');

        $response = $this->postJson('/v1/documents', [
            'category' => Document::CATEGORY_PERSONAL,
            'title' => 'Contrato',
            'notes' => 'Upload do colaborador',
            'files' => [$file],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.0.storage_disk', 's3')
            ->assertJsonPath('data.0.original_name', 'contrato.pdf');

        $document = Document::query()->findOrFail($response->json('data.0.id'));

        $prefix = "jornafy-documents/{$employee->company_id}/documents/employees/{$employee->id}/";
        $this->assertStringStartsWith($prefix, $document->path);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}\.pdf$/', basename($document->path));

        Storage::disk('s3')->assertExists($document->path);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'storage_disk' => 's3',
            'path' => $document->path,
            'original_name' => 'contrato.pdf',
            'uploaded_by' => $employee->id,
        ]);
    }

    public function test_employee_resend_moves_document_to_s3_and_updates_metadata(): void
    {
        $employee = $this->createEmployee();

        Sanctum::actingAs($employee, ['*']);
        Storage::fake('s3');
        Storage::fake('local');

        $oldPath = "private/documents/{$employee->company_id}/{$employee->id}/old-file.pdf";
        Storage::disk('local')->put($oldPath, 'old-content');

        $document = Document::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'title' => 'Documento em revisão',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_REVIEW,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 100,
            'path' => $oldPath,
            'storage_disk' => 'local',
            'original_name' => 'old-file.pdf',
            'uploaded_by' => $employee->id,
        ]);

        $newFile = $this->fakePdf('novo.pdf');

        $response = $this->postJson("/v1/documents/{$document->id}/resend", [
            'file' => $newFile,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.storage_disk', 's3')
            ->assertJsonPath('data.original_name', 'novo.pdf')
            ->assertJsonPath('data.status', Document::STATUS_PENDING);

        $document->refresh();

        $prefix = "jornafy-documents/{$employee->company_id}/documents/employees/{$employee->id}/";
        $this->assertStringStartsWith($prefix, $document->path);
        Storage::disk('s3')->assertExists($document->path);
        Storage::disk('local')->assertMissing($oldPath);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'storage_disk' => 's3',
            'path' => $document->path,
            'original_name' => 'novo.pdf',
            'uploaded_by' => $employee->id,
            'status' => Document::STATUS_PENDING,
        ]);
    }

    public function test_download_redirects_to_presigned_url_for_s3_documents(): void
    {
        $employee = $this->createEmployee();
        Sanctum::actingAs($employee, ['*']);

        $path = "jornafy-documents/{$employee->company_id}/documents/employees/{$employee->id}/01JTESTABCDEFGHJKMNPQRST.pdf";

        $document = Document::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'title' => 'Documento S3',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_AVAILABLE,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 321,
            'path' => $path,
            'storage_disk' => 's3',
            'original_name' => 'doc.pdf',
            'uploaded_by' => $employee->id,
        ]);

        $signedUrl = 'https://example-s3.local/temp-signed-download';

        $mockDisk = Mockery::mock(FilesystemAdapter::class);
        $mockDisk->shouldReceive('exists')->twice()->with($path)->andReturn(true);
        $mockDisk->shouldReceive('temporaryUrl')->once()->andReturn($signedUrl);

        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($mockDisk);

        $response = $this->get("/v1/documents/{$document->id}/download");

        $response->assertRedirect($signedUrl);
    }

    public function test_download_keeps_legacy_behavior_for_local_documents(): void
    {
        $employee = $this->createEmployee();

        Sanctum::actingAs($employee, ['*']);
        Storage::fake('local');

        $path = "private/documents/{$employee->company_id}/{$employee->id}/legacy.pdf";
        Storage::disk('local')->put($path, 'legacy-content');

        $document = Document::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'title' => 'Legacy Document',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_AVAILABLE,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 64,
            'path' => $path,
            'storage_disk' => 'local',
            'original_name' => 'legacy.pdf',
            'uploaded_by' => $employee->id,
        ]);

        $response = $this->get("/v1/documents/{$document->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control', ''));
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['employee']);

        return $user;
    }

    private function fakePdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
        );
    }
}
