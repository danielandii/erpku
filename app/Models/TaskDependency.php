<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// TaskDependency
// ══════════════════════════════════════════════════════════
class TaskDependency extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'depends_on_task_id', 'dependency_type',
    ];

    // FS = Finish-to-Start | SS = Start-to-Start
    // FF = Finish-to-Finish | SF = Start-to-Finish
    const TYPE_FS = 'FS';
    const TYPE_SS = 'SS';
    const TYPE_FF = 'FF';
    const TYPE_SF = 'SF';

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function dependsOnTask()
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}
