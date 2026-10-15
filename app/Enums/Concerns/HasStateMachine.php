<?php

namespace App\Enums\Concerns;

/**
 * Implementa uma máquina de estados simples sobre um Enum.
 *
 * O Enum declara, em transitions(), para quais status cada status pode ir.
 * As Actions consultam canTransitionTo() antes de alterar um registro, o que
 * centraliza a regra de negócio e impede transições inválidas
 * (ex.: reabrir um contrato cancelado).
 */
trait HasStateMachine
{
    /**
     * @return list<self>
     */
    abstract public function transitions(): array;

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->transitions() === [];
    }
}
