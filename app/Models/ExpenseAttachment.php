<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    protected $fillable = ['expense_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    // the storage path never leaves the server; the file is fetched through an authorised endpoint
    protected $hidden = ['path'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
