<?php

namespace App\Actions\Crm;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\DB;

/**
 * Move uma oportunidade no funil (arrastar no Kanban ou editar a etapa).
 *
 * - Atualiza a etapa e a posição dentro da coluna (persistido no banco).
 * - Ao mudar de etapa, assume a probabilidade padrão da nova etapa.
 * - Etapas de "ganho"/"perda" encerram a oportunidade; voltar para uma
 *   etapa comum a reabre.
 */
class MoveOpportunity
{
    public function handle(Opportunity $opportunity, PipelineStage $stage, ?int $position = null, ?string $lostReason = null): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $stage, $position, $lostReason): Opportunity {
            $previousStatus = $opportunity->status;
            $stageChanged = $opportunity->pipeline_stage_id !== $stage->id;

            $newStatus = match (true) {
                $stage->is_won => OpportunityStatus::Won,
                $stage->is_lost => OpportunityStatus::Lost,
                default => OpportunityStatus::Open,
            };

            $siblings = Opportunity::query()
                ->where('pipeline_stage_id', $stage->id)
                ->whereKeyNot($opportunity->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $position = $position === null ? count($siblings) : max(0, min($position, count($siblings)));
            array_splice($siblings, $position, 0, [$opportunity->id]);

            $opportunity->fill([
                'pipeline_stage_id' => $stage->id,
                'status' => $newStatus,
                'probability' => $stageChanged ? $stage->probability : $opportunity->probability,
                'closed_at' => $newStatus === OpportunityStatus::Open ? null : ($opportunity->closed_at ?? now()),
                'lost_reason' => $newStatus === OpportunityStatus::Lost ? ($lostReason ?? $opportunity->lost_reason) : null,
            ])->save();

            // Reordena a coluna de destino em uma única passada.
            foreach ($siblings as $index => $id) {
                Opportunity::query()->whereKey($id)->where('position', '!=', $index)->update(['position' => $index]);
            }

            $opportunity->position = $position;

            if ($previousStatus !== $newStatus) {
                $opportunity->recordStatusChange($previousStatus, $newStatus, $lostReason);
            }

            return $opportunity;
        });
    }
}
