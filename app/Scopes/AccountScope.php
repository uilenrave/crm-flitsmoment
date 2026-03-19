<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope voor multi-tenant isolatie.
 * Filtert automatisch alle queries op account_id.
 */
class AccountScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $accountId = session('account_id');

        if ($accountId) {
            $builder->where($model->getTable() . '.account_id', $accountId);
        }
    }
}
