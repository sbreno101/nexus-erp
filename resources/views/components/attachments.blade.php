{{--
    Painel de documentos/anexos de qualquer registro que use o trait HasAttachments.
      <x-attachments :model="$customer" :attachments="$customer->attachments" />
--}}
@props(['model', 'attachments'])

@php($canAttach = auth()->user()->can('attach', $model))

<x-ui.card title="Documentos e anexos" :padding="false">
    @if ($canAttach)
        <x-slot:actions>
            <x-ui.button type="button" size="sm" variant="secondary" icon="arrow-up-tray" x-data x-on:click="$dispatch('open-modal', 'upload-attachment')">Anexar arquivo</x-ui.button>
        </x-slot:actions>
    @endif

    @forelse ($attachments as $attachment)
        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
            <x-icon name="paper-clip" class="size-5 text-slate-400" />
            <div class="min-w-0 flex-1">
                <a href="{{ route('attachments.download', $attachment) }}" class="link block truncate text-sm">{{ $attachment->original_name }}</a>
                <p class="text-xs text-slate-500">
                    {{ $attachment->humanSize() }} · {{ br_date($attachment->created_at, true) }}
                    @if ($attachment->uploader) · {{ $attachment->uploader->name }} @endif
                    @if ($attachment->description) · {{ $attachment->description }} @endif
                </p>
            </div>
            @if ($canAttach)
                <form method="POST" action="{{ route('attachments.destroy', $attachment) }}" x-data x-on:submit="if (! confirm('Remover este anexo?')) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded p-1 text-slate-400 hover:text-red-600" aria-label="Remover anexo {{ $attachment->original_name }}">
                        <x-icon name="trash" class="size-4" />
                    </button>
                </form>
            @endif
        </div>
    @empty
        <x-ui.empty-state icon="paper-clip" title="Nenhum documento anexado" description="Contratos assinados, comprovantes e outros arquivos aparecem aqui." />
    @endforelse
</x-ui.card>

@if ($canAttach)
    <x-ui.modal name="upload-attachment" title="Anexar arquivo">
        <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <input type="hidden" name="attachable_type" value="{{ $model->getMorphClass() }}">
            <input type="hidden" name="attachable_id" value="{{ $model->getKey() }}">

            <div>
                <label for="attachment-file" class="form-label">Arquivo <span class="text-red-500">*</span></label>
                <input id="attachment-file" type="file" name="file" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-slate-300 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                <p class="form-help">PDF, imagens, Office, CSV, TXT, XML ou ZIP — até 10 MB.</p>
                @error('file') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-form.input name="description" label="Descrição (opcional)" maxlength="255" />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'upload-attachment')">Cancelar</x-ui.button>
                <x-ui.button x-bind:disabled="submitting">Enviar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif
