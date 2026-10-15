<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento/anexo armazenado no disco PRIVADO ("local" => storage/app/private).
 *
 * Arquivos nunca ficam em public/: o download passa pelo AttachmentController,
 * que verifica a autorização sobre o registro "dono" antes de entregar o arquivo.
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'description', 'uploaded_by'])]
class Attachment extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Tamanho legível: 1536 => "1,5 KB".
     */
    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', '.').' '.$units[$unit];
    }
}
