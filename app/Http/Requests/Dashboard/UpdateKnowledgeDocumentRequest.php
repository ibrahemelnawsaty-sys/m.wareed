<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

/**
 * Same validation contract as creating a document. Kept as a distinct class so
 * the two paths can diverge later without touching call sites.
 */
class UpdateKnowledgeDocumentRequest extends StoreKnowledgeDocumentRequest {}
