<?php

namespace App\Actions\Crm;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou atualiza uma oportunidade. Mudanças de etapa passam sempre pelo
 * MoveOpportunity, para que as regras do funil (status, probabilidade,
 * data de fechamento e histórico) sejam as mesmas do Kanban.
 */
class SaveOpportunity
{
    public function __construct(private MoveOpportunity $moveOpportunity) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Opportunity
    {
        return DB::transaction(function () use ($data): Opportunity {
            $stage = PipelineStage::query()->findOrFail($data['pipeline_stage_id']);

            $opportunity = Opportunity::create([
                ...$data,
                'status' => OpportunityStatus::Open,
                'probability' => $data['probability'] ?? $stage->probability,
                'position' => Opportunity::query()->where('pipeline_stage_id', $stage->id)->count(),
            ]);

            // Se a etapa inicial já for de ganho/perda, aplica as regras do funil.
            if ($stage->isClosing()) {
                $this->moveOpportunity->handle($opportunity, $stage);
            }

            return $opportunity;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $data): Opportunity {
            $opportunity->update(Arr::except($data, ['pipeline_stage_id']));

            if ((int) $data['pipeline_stage_id'] !== $opportunity->pipeline_stage_id) {
                $stage = PipelineStage::query()->findOrFail($data['pipeline_stage_id']);
                $this->moveOpportunity->handle($opportunity, $stage, lostReason: $data['lost_reason'] ?? null);
            }

            return $opportunity;
        });
    }
}
