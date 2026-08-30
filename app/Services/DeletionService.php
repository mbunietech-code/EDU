<?php

namespace App\Services;

use App\Models\DeletedRecord;
use Illuminate\Database\Eloquent\Model;

class DeletionService
{
    /**
     * Record a snapshot of the model being deleted (with the admin's reason),
     * then permanently delete it. Nothing is ever silently lost — every
     * deletion across the admin panel is recoverable for reference here.
     */
    public function delete(Model $model, string $reason): DeletedRecord
    {
        $record = DeletedRecord::create([
            'entity' => class_basename($model),
            'entity_id' => $model->getKey(),
            'label' => $this->labelFor($model),
            'reason' => $reason,
            'snapshot' => $model->toArray(),
            'deleted_by' => auth()->id(),
        ]);

        $model->delete();

        return $record;
    }

    protected function labelFor(Model $model): string
    {
        foreach (['order_number', 'name', 'title', 'label', 'subject', 'code'] as $field) {
            if (! empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }
}
