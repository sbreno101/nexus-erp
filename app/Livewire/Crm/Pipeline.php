<?php

namespace App\Livewire\Crm;

use App\Actions\Crm\MoveOpportunity;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Funil de vendas em formato Kanban.
 *
 * O arrastar-e-soltar usa a diretiva x-sort do Alpine (incluída no
 * Livewire): ao soltar um cartão, o navegador chama move() com o id da
 * oportunidade, a nova posição e a etapa de destino. O servidor valida a
 * permissão, persiste a mudança (MoveOpportunity) e a tela é re-renderizada.
 */
#[Title('Funil de vendas')]
class Pipeline extends Component
{
    #[Url(as: 'responsavel', except: '')]
    public string $owner = '';

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /**
     * Exibir oportunidades ganhas/perdidas dos últimos 30 dias nas colunas finais.
     */
    #[Url(as: 'encerradas', except: false)]
    public bool $showClosed = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Opportunity::class);
    }

    public function move(int $opportunityId, int $position, int $stageId, MoveOpportunity $moveOpportunity): void
    {
        $opportunity = Opportunity::query()->findOrFail($opportunityId);
        $this->authorize('update', $opportunity);

        $stage = PipelineStage::query()->findOrFail($stageId);
        $moveOpportunity->handle($opportunity, $stage, $position);

        if ($stage->isClosing()) {
            $this->dispatch('notify', type: 'success', message: "Oportunidade marcada como {$opportunity->status->label()}.");
        }
    }

    public function render(): View
    {
        $stages = PipelineStage::query()->ordered()->get();

        $opportunities = Opportunity::query()
            ->with(['customer:id,name,trade_name', 'lead:id,name,company_name', 'owner:id,name'])
            ->when($this->owner === 'me', fn ($query) => $query->where('owner_id', auth()->id()))
            ->when(ctype_digit($this->owner), fn ($query) => $query->where('owner_id', $this->owner))
            ->when(trim($this->search) !== '', fn ($query) => $query->where('title', 'ilike', '%'.trim($this->search).'%'))
            ->where(function ($query): void {
                $query->where('status', 'open');

                if ($this->showClosed) {
                    $query->orWhere('closed_at', '>=', now()->subDays(30));
                }
            })
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('pipeline_stage_id');

        return view('livewire.crm.pipeline', [
            'stages' => $stages,
            'opportunities' => $opportunities,
            'owners' => User::optionsForCompany(app(CurrentCompany::class)->id()),
            'canUpdate' => auth()->user()->hasPermission('opportunities.update'),
        ]);
    }
}
