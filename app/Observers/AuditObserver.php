<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Writes one audit_logs row whenever a watched record is created, changed or deleted - who did it, from
 * where, and the values before and after. Passwords and tokens are never written. A save that changes only
 * the timestamps is not an event. The log is append-only; nothing here ever updates or deletes a row.
 */
class AuditObserver
{
    private const NEVER = ['password', 'remember_token', 'updated_at', 'created_at', 'deleted_at', 'attachment_path'];

    public function created(Model $model): void
    {
        $this->write('created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = array_diff_key($model->getChanges(), array_flip(self::NEVER));
        if ($changes === []) {
            return;
        }
        $old = array_intersect_key($model->getOriginal(), $changes);
        $this->write('updated', $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write('deleted', $model, $model->getOriginal(), []);
    }

    private static ?bool $ready = null;

    private function write(string $event, Model $model, array $old, array $new): void
    {
        // a server upgraded in two steps (code first, tables after) must keep working in between
        self::$ready ??= Schema::hasTable('audit_logs');
        if (! self::$ready) {
            return;
        }

        $strip = fn (array $a) => array_diff_key($a, array_flip(self::NEVER));

        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => class_basename($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $strip($old) ?: null,
            'new_values' => $strip($new) ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
