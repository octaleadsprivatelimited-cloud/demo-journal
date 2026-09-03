<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes !== []) {
            $old = array_intersect_key($model->getRawOriginal(), $changes);
            $this->audit->record($model, 'updated', $old, $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->audit->record($model, 'deleted', $model->getRawOriginal(), []);
    }

    public function restored(Model $model): void
    {
        $this->audit->record($model, 'restored', [], $model->getAttributes());
    }
}
