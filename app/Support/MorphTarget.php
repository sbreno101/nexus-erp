<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolve com segurança o registro "dono" de anexos, interações e tarefas
 * a partir do par (tipo, id) enviado pelo formulário.
 *
 * Só aceita os tipos explicitamente permitidos (nunca um nome de classe
 * arbitrário vindo do navegador) e busca o registro pelo Model — portanto
 * respeitando o CompanyScope: um id de outra empresa resulta em 404.
 */
class MorphTarget
{
    /**
     * @param  list<string>  $allowedTypes  apelidos do morph map (ex.: "customer")
     */
    public static function resolve(string $type, int|string $id, array $allowedTypes): Model
    {
        if (! in_array($type, $allowedTypes, true)) {
            throw new NotFoundHttpException('Tipo de registro inválido.');
        }

        /** @var class-string<Model>|null $class */
        $class = Relation::getMorphedModel($type);

        if ($class === null) {
            throw new NotFoundHttpException('Tipo de registro inválido.');
        }

        return $class::query()->findOrFail($id);
    }
}
