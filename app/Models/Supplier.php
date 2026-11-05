<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    /** Category code (fits the varchar(12) column) => Bengali name. Gas and materials have purchase books; the rest are paid through ordinary expenses. */
    public const LABELS = [
        'GAS' => 'গ্যাস',
        'MATERIALS' => 'নির্মাণ সামগ্রী',
        'ELECTRIC' => 'বৈদ্যুতিক',
        'PLUMBING' => 'প্লাম্বিং ও পানি',
        'LIFT' => 'লিফট',
        'GENERATOR' => 'জেনারেটর ও জ্বালানি',
        'CLEANING' => 'পরিচ্ছন্নতা',
        'SECURITY' => 'নিরাপত্তা ও সিসিটিভি',
        'REPAIR' => 'মেরামত ও রক্ষণাবেক্ষণ',
        'OFFICE' => 'অফিস ও স্টেশনারি',
        'OTHER' => 'অন্যান্য',
    ];

    public const KINDS = ['GAS', 'MATERIALS', 'ELECTRIC', 'PLUMBING', 'LIFT', 'GENERATOR', 'CLEANING', 'SECURITY', 'REPAIR', 'OFFICE', 'OTHER'];

    /** Every category except the two that keep their own purchase books. */
    public const OTHER_KINDS = ['ELECTRIC', 'PLUMBING', 'LIFT', 'GENERATOR', 'CLEANING', 'SECURITY', 'REPAIR', 'OFFICE', 'OTHER'];

    protected $fillable = ['name', 'kind', 'phone', 'address', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
