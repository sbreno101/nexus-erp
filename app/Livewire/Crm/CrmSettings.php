<?php

namespace App\Livewire\Crm;

use App\Models\LeadSource;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configuração do CRM: etapas do funil (ordem, probabilidade, cor, ganho/perda)
 * e origens de leads. Tudo por empresa.
 */
#[Title('Configurações do CRM')]
class CrmSettings extends Component
{
    public string $stageName = '';

    public int $stageProbability = 50;

    public string $stageColor = '#6366f1';

    public string $sourceName = '';

    /**
     * Edição inline das etapas: [id => [name, probability, color, is_won, is_lost]].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $stages = [];

    public function mount(): void
    {
        $this->authorize('crm.configure');
        $this->loadStages();
    }

    public function addStage(): void
    {
        $this->authorize('crm.configure');

        $validated = $this->validate([
            'stageName' => ['required', 'string', 'max:100', Rule::unique('pipeline_stages', 'name')->where('company_id', app(CurrentCompany::class)->id())],
            'stageProbability' => ['required', 'integer', 'between:0,100'],
            'stageColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], attributes: ['stageName' => 'nome da etapa', 'stageProbability' => 'probabilidade', 'stageColor' => 'cor']);

        // Novas etapas entram antes das etapas de encerramento (ganha/perdida).
        $position = (int) PipelineStage::query()->where('is_won', false)->where('is_lost', false)->max('position') + 1;
        PipelineStage::query()->where('position', '>=', $position)->increment('position');

        PipelineStage::create([
            'name' => $validated['stageName'],
            'probability' => $validated['stageProbability'],
            'color' => $validated['stageColor'],
            'position' => $position,
        ]);

        $this->reset('stageName', 'stageProbability', 'stageColor');
        $this->loadStages();
        $this->dispatch('notify', type: 'success', message: 'Etapa criada.');
    }

    public function saveStage(int $stageId): void
    {
        $this->authorize('crm.configure');

        $this->validate([
            "stages.{$stageId}.name" => ['required', 'string', 'max:100', Rule::unique('pipeline_stages', 'name')->where('company_id', app(CurrentCompany::class)->id())->ignore($stageId)],
            "stages.{$stageId}.probability" => ['required', 'integer', 'between:0,100'],
            "stages.{$stageId}.color" => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], attributes: ["stages.{$stageId}.name" => 'nome', "stages.{$stageId}.probability" => 'probabilidade']);

        $data = $this->stages[$stageId];

        PipelineStage::query()->findOrFail($stageId)->update([
            'name' => $data['name'],
            'probability' => (int) $data['probability'],
            'color' => $data['color'],
            'is_won' => (bool) $data['is_won'],
            'is_lost' => (bool) $data['is_lost'] && ! $data['is_won'],
        ]);

        $this->loadStages();
        $this->dispatch('notify', type: 'success', message: 'Etapa atualizada.');
    }

    public function moveStage(int $stageId, string $direction): void
    {
        $this->authorize('crm.configure');

        $ordered = PipelineStage::query()->ordered()->pluck('id')->all();
        $index = array_search($stageId, $ordered, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! isset($ordered[$target])) {
            return;
        }

        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $position => $id) {
            PipelineStage::query()->whereKey($id)->update(['position' => $position]);
        }

        $this->loadStages();
    }

    public function deleteStage(int $stageId): void
    {
        $this->authorize('crm.configure');

        if (Opportunity::withTrashed()->where('pipeline_stage_id', $stageId)->exists()) {
            $this->dispatch('notify', type: 'error', message: 'Esta etapa possui oportunidades. Mova-as para outra etapa antes de excluir.');

            return;
        }

        if (PipelineStage::query()->count() <= 2) {
            $this->dispatch('notify', type: 'error', message: 'O funil precisa ter pelo menos duas etapas.');

            return;
        }

        PipelineStage::query()->findOrFail($stageId)->delete();
        $this->loadStages();
        $this->dispatch('notify', type: 'success', message: 'Etapa excluída.');
    }

    public function addSource(): void
    {
        $this->authorize('crm.configure');

        $validated = $this->validate([
            'sourceName' => ['required', 'string', 'max:100', Rule::unique('lead_sources', 'name')->where('company_id', app(CurrentCompany::class)->id())],
        ], attributes: ['sourceName' => 'origem']);

        LeadSource::create(['name' => $validated['sourceName'], 'is_active' => true]);
        $this->reset('sourceName');
        $this->dispatch('notify', type: 'success', message: 'Origem criada.');
    }

    public function toggleSource(int $sourceId): void
    {
        $this->authorize('crm.configure');

        $source = LeadSource::query()->findOrFail($sourceId);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function render(): View
    {
        return view('livewire.crm.crm-settings', [
            'sources' => LeadSource::query()->withCount('leads')->orderBy('name')->get(),
            'stageCounts' => Opportunity::query()->selectRaw('pipeline_stage_id, COUNT(*) AS total')->groupBy('pipeline_stage_id')->pluck('total', 'pipeline_stage_id'),
        ]);
    }

    private function loadStages(): void
    {
        $this->stages = PipelineStage::query()->ordered()->get()
            ->mapWithKeys(fn (PipelineStage $stage) => [$stage->id => [
                'name' => $stage->name,
                'probability' => $stage->probability,
                'color' => $stage->color,
                'is_won' => $stage->is_won,
                'is_lost' => $stage->is_lost,
            ]])->all();
    }
}
