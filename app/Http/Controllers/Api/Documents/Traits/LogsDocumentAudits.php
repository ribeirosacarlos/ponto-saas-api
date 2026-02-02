<?php

namespace App\Http\Controllers\Api\Documents\Traits;

use App\Models\Document;
use App\Models\DocumentAudit;

trait LogsDocumentAudits
{
    protected function logDocumentAudit(Document $document, string $action, array $meta = []): DocumentAudit
    {
        return DocumentAudit::create([
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
