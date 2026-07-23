<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Models\P2pDispute;
use App\Models\P2pDisputeEvidence;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Attach a piece of evidence (a file, with an optional note) to a dispute. Files
 * live on the private `local` disk and are only served via an authorised route.
 */
class AddDisputeEvidenceAction
{
    public function execute(P2pDispute $dispute, string $uploaderRole, string $uploaderId, UploadedFile $file, ?string $note = null): P2pDisputeEvidence
    {
        if (! $dispute->status->isOpen()) {
            throw new RuntimeException('This dispute is already resolved.');
        }

        $path = $file->store("p2p-disputes/{$dispute->id}", 'local');

        return P2pDisputeEvidence::create([
            'dispute_id' => $dispute->id,
            'uploaded_by' => $uploaderId,
            'uploader_role' => $uploaderRole,
            'path' => $path,
            'note' => $note,
        ]);
    }
}
