<?php

namespace App\Listeners;

use App\Events\WorkItemAssigned;
use App\Models\ServiceOrder;
use App\Notifications\WorkItemAssignedNotification;

/**
 * Notifica o responsável por uma OS/chamado recém-atribuído (exceto quando
 * a pessoa atribuiu a si mesma).
 */
class NotifyWorkItemAssignee
{
    public function handle(WorkItemAssigned $event): void
    {
        if ($event->assignee->is($event->assignedBy)) {
            return;
        }

        $settings = $event->item->company()->firstOrFail()->settingsOrDefault();
        $sendMail = $event->item instanceof ServiceOrder
            ? $settings->notify_service_order_assignment
            : $settings->notify_ticket_assignment;

        $event->assignee->notify(new WorkItemAssignedNotification($event->item, $sendMail));
    }
}
