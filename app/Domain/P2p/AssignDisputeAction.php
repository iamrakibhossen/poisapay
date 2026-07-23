<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Audit\ActivityLogger;
use App\Enums\P2pDisputeStatus;
use App\Models\Admin;
use App\Models\P2pDispute;
use RuntimeException;

/** Operator takes a dispute: assigns it to themselves and marks it Under Review. */
class AssignDisputeAction
{
    public function execute(P2pDispute $dispute, Admin $admin): P2pDispute
    {
        if (! $dispute->status->isOpen()) {
            throw new RuntimeException('This dispute is already resolved.');
        }

        $dispute->update([
            'status' => P2pDisputeStatus::UnderReview,
            'assigned_admin_id' => $admin->getKey(),
        ]);

        ActivityLogger::log('p2p.dispute.assigned', $dispute, [], actor: $admin);

        return $dispute;
    }
}
