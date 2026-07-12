<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffMailboxProcessedMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'staff_mailbox_id',
        'message_uid',
        'message_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(StaffMailbox::class, 'staff_mailbox_id');
    }
}
